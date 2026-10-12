<?php

namespace App\Services;

use App\Models\Organizer;
use App\Models\Vendor;
use App\Models\VendorLoginToken;
use Illuminate\Support\Str;

/**
 * Passwordless vendor sign-in: a one-time link sent by email, valid for 30
 * minutes. The session cookie is shared across subdomains, so signing in on
 * the directory also fills in application forms on every organizer's site.
 */
class VendorAuth
{
    private const SESSION_KEY = 'vendor_id';

    public static function current(): ?Vendor
    {
        $id = session(self::SESSION_KEY);

        return $id ? Vendor::find($id) : null;
    }

    public static function login(Vendor $vendor): void
    {
        session()->regenerate();
        session([self::SESSION_KEY => $vendor->id]);
        $vendor->forceFill(['last_login_at' => now()])->save();
    }

    public static function logout(): void
    {
        session()->forget(self::SESSION_KEY);
        session()->regenerate();
    }

    /** Emails a sign-in link and returns it (shown on screen in local mode only). */
    public static function sendLink(string $email, string $ip): string
    {
        $email = Str::lower(trim($email));
        $token = Str::random(48);
        VendorLoginToken::create([
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'ip' => $ip,
            'expires_at' => now()->addMinutes(30),
        ]);
        $url = Organizer::directoryUrl('/vendor/login/'.$token);
        Notifier::send(null, $email, 'Your sign-in link for '.config('platform.name'), [
            'Use the link below to sign in to your vendor account. It works once and expires in 30 minutes.',
            "If you didn't ask for this, you can ignore this email.",
        ], ['label' => 'Sign in', 'url' => $url]);

        return $url;
    }

    /** Uses a sign-in token. Returns the vendor, or null if the link is bad, used or expired. */
    public static function consume(string $token): ?Vendor
    {
        $row = VendorLoginToken::where('token_hash', hash('sha256', $token))->first();
        if (! $row || $row->used_at || $row->expires_at->isPast()) {
            return null;
        }
        $row->update(['used_at' => now()]);

        return Vendor::findOrCreateByEmail($row->email);
    }
}
