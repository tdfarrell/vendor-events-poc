<?php

namespace App\Http\Controllers\Directory;

use App\Http\Controllers\Controller;
use App\Models\VendorApplication;
use App\Services\VendorAuth;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;

class VendorAccountController extends Controller
{
    public function loginForm()
    {
        if (VendorAuth::current()) {
            return redirect()->route('vendor.account');
        }

        return view('vendor.login');
    }

    public function sendLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190']]);
        $url = VendorAuth::sendLink($data['email'], (string) $request->ip());

        return view('vendor.login-sent', [
            'email' => $data['email'],
            'devLink' => config('platform.show_login_links') ? $url : null,
        ]);
    }

    /** A confirm button, so email scanners that open links don't use up the token. */
    public function confirm(string $token)
    {
        return view('vendor.login-confirm', compact('token'));
    }

    public function consume(string $token)
    {
        $vendor = VendorAuth::consume($token);
        if (! $vendor) {
            return redirect()->route('vendor.login')->with('error', 'That sign-in link has expired or was already used. Send yourself a new one.');
        }
        VendorAuth::login($vendor);

        return redirect()->route('vendor.account');
    }

    public function logout()
    {
        VendorAuth::logout();

        return redirect()->route('directory.events');
    }

    public function account()
    {
        $vendor = VendorAuth::current() ?? abort(redirect()->route('vendor.login'));

        // A vendor's own bookings, with every organizer.
        $bookings = Tenancy::acrossOrganizers(fn () => VendorApplication::with(['event.organizer', 'tableType'])
            ->where('vendor_id', $vendor->id)
            ->whereNotIn('status', ['checkout', 'incomplete'])
            ->latest()
            ->get());

        return view('vendor.account', compact('vendor', 'bookings'));
    }

    public function update(Request $request)
    {
        $vendor = VendorAuth::current() ?? abort(redirect()->route('vendor.login'));
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:200'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'town' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'],
            'categories' => ['nullable', 'string', 'max:500'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'listed' => ['nullable', 'boolean'],
        ]);
        $data['listed'] = $request->boolean('listed');
        $vendor->fill(array_map(fn ($v) => $v ?? '', $data))->save();
        $vendor->assignSlug();

        return back()->with('status', 'Profile saved.');
    }
}
