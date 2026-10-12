@extends('layouts.admin')
@section('page', 'Settings')

@section('content')
<h1>Settings</h1>
<div class="cols">
    <form method="post" action="{{ route('admin.settings') }}" class="card">
        @csrf @method('put')
        <h2>Branding</h2>
        @unless (auth()->user()->isOwner())<div class="alert info">Only the account owner can change these.</div>@endunless
        <div class="field"><label>Organizer name</label><input type="text" name="name" required value="{{ old('name', $org->name) }}"></div>
        <div class="field"><label>Tagline</label><input type="text" name="tagline" value="{{ old('tagline', $org->tagline) }}"></div>
        <div class="field">
            <label>Brand color</label>
            <div class="row"><input type="color" name="brand_color" value="{{ old('brand_color', $org->brand_color) }}"><span class="muted">Used for your header, buttons and event cards in the directory.</span></div>
        </div>
        <div class="fields-2">
            <div class="field"><label>Contact email <span class="hint">replies and new-vendor alerts</span></label><input type="email" name="contact_email" value="{{ old('contact_email', $org->contact_email) }}"></div>
            <div class="field"><label>Emails come from</label><input type="text" name="email_from_name" value="{{ old('email_from_name', $org->email_from_name) }}" placeholder="{{ $org->name }}"></div>
        </div>
        <div class="fields-2">
            <div class="field"><label>Home town</label><input type="text" name="town" value="{{ old('town', $org->town) }}"></div>
            <div class="field"><label>Website</label><input type="text" name="website" value="{{ old('website', $org->website) }}"></div>
        </div>
        <button class="btn primary" @disabled(! auth()->user()->isOwner())>Save settings</button>
    </form>
    <div class="stack">
        <div class="card">
            <h3>Your address</h3>
            <p class="mono" style="word-break:break-all">{{ $org->url('/') }}</p>
            <p class="muted" style="font-size:.88rem">Custom domains come later.</p>
        </div>
        <div class="card">
            <h3>Team</h3>
            @foreach ($staff as $u)
                <div class="row between" style="padding:6px 0"><span>{{ $u->name }}<br><small>{{ $u->email }}</small></span><span class="badge">{{ $u->role }}</span></div>
            @endforeach
        </div>
        <div class="card">
            <h3>Payments</h3>
            <p class="muted" style="font-size:.9rem">Not connected. In this proof of concept, payments go through a test checkout. The full build connects your own Stripe account so payouts go straight to you.</p>
        </div>
    </div>
</div>
@endsection
