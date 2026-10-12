<?php

namespace App\Http\Controllers\Directory;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\User;
use App\Services\Notifier;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Organizer sign-up. Creates the organizer and its owner account. On shared
 * hosting each new subdomain still needs a DNS record and certificate, so new
 * organizers start as "pending" until someone on the platform side marks the
 * address live.
 */
class SignupController extends Controller
{
    public function form()
    {
        return view('signup.form');
    }

    public function store(Request $request)
    {
        $request->merge(['slug' => Str::lower(trim((string) $request->input('slug')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn(config('platform.reserved_slugs')), Rule::unique('organizers', 'slug')],
            'town' => ['nullable', 'string', 'max:100'],
            'focus' => ['nullable', Rule::in(array_keys(config('event_types')))],
            'owner_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:200'],
            'agree' => ['accepted'],
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and dashes only.',
            'slug.unique' => 'That address is taken. Try another.',
            'slug.not_in' => 'That address is reserved. Try another.',
            'email.unique' => 'That email already has an organizer account. Sign in on your own site instead.',
            'agree.accepted' => 'Please agree to the demo terms to continue.',
        ]);

        $palette = ['#FF6A2B', '#4A3BB5', '#1F7A5C', '#E8336D', '#1F8FD6', '#C2781A'];
        $org = DB::transaction(function () use ($data, $palette) {
            $org = Organizer::create([
                'name' => $data['name'], 'slug' => $data['slug'], 'town' => $data['town'] ?? '',
                'contact_email' => $data['email'], 'brand_color' => $palette[crc32($data['slug']) % count($palette)],
                'status' => 'pending',
            ]);
            Tenancy::as($org, fn () => User::create([
                'name' => $data['owner_name'], 'email' => Str::lower($data['email']), 'password' => $data['password'], 'role' => 'owner',
            ]));

            return $org;
        });

        Notifier::send($org, $data['email'], 'Welcome to '.config('platform.name'), [
            "Hi {$data['owner_name']},",
            "{$org->name} is set up. Your site will be at ".$org->url('/').' as soon as the address is live; we\'ll email you when it is, usually within a day.',
            'Once it is, sign in with this email and the password you chose, and create your first event.',
        ], ['label' => 'Your admin', 'url' => $org->url('/admin')], 'welcome');

        if ($to = config('platform.admin_email')) {
            Notifier::send(null, $to, "New organizer: {$org->name}", [
                "{$data['owner_name']} ({$data['email']}) signed up {$org->name} at {$org->slug}.".config('platform.domain').'.',
                'To make it live: add the subdomain in cPanel pointing at the site folder, add an A record for it in DNS, run AutoSSL, then mark it live in platform admin.',
            ], ['label' => 'Platform admin', 'url' => route('platform.dashboard')], 'signup');
        }

        return redirect()->route('signup.welcome')->with('new_org', $org->id);
    }

    public function welcome()
    {
        $org = Organizer::find(session('new_org'));
        abort_unless($org, 404);

        return view('signup.welcome', compact('org'));
    }
}
