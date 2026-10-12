@extends('layouts.directory')
@section('title', 'Host your event on '.config('platform.name'))

@section('before')
<section class="dir-hero" style="padding-bottom:64px">
    <div class="wrap">
        <h1 style="max-width:18ch">Run your show without the spreadsheet</h1>
        <p class="lead">Vendor bookings, a floor plan, tickets and a door app that counts cash. Free for organizers; {{ config('platform.name') }} keeps a small fee on online payments only.</p>
    </div>
</section>
@endsection

@section('content')
<div class="cols">
    <form method="post" action="{{ route('signup') }}" class="card lift">
        @csrf
        <h2>Set up your organizer account</h2>
        <fieldset>
            <legend>Your events</legend>
            <div class="field"><label for="name">Organizer or group name</label><input id="name" type="text" name="name" required value="{{ old('name') }}" placeholder="Shoreline Craft Collective"></div>
            <div class="field">
                <label for="slug">Your web address</label>
                <div class="row" style="gap:6px;flex-wrap:nowrap"><input id="slug" type="text" name="slug" required value="{{ old('slug') }}" placeholder="shoreline" style="max-width:220px" pattern="[a-z0-9-]{3,30}" autocapitalize="none"><span class="muted">.{{ config('platform.domain') }}</span></div>
                <div class="hint">Lowercase letters, numbers and dashes.</div>
            </div>
            <div class="fields-2">
                <div class="field"><label>Home town</label><input type="text" name="town" value="{{ old('town') }}"></div>
                <div class="field"><label>Mostly run</label>
                    <select name="focus"><option value="">A mix</option>@foreach (config('event_types') as $k => $t)<option value="{{ $k }}" @selected(old('focus') === $k)>{{ $t['plural'] }}</option>@endforeach</select>
                </div>
            </div>
        </fieldset>
        <fieldset>
            <legend>Your sign-in</legend>
            <div class="field"><label>Your name</label><input type="text" name="owner_name" required value="{{ old('owner_name') }}"></div>
            <div class="field"><label>Email</label><input type="email" name="email" required value="{{ old('email') }}" autocomplete="email"></div>
            <div class="field"><label>Password <span class="hint">at least 8 characters</span></label><input type="password" name="password" required minlength="8" autocomplete="new-password"></div>
        </fieldset>
        <label class="check" style="margin-bottom:16px"><input type="checkbox" name="agree" value="1" required> I understand this is a demo: payments are simulated and email may not be delivered.</label>
        <button class="btn fire big block">Create my account</button>
    </form>
    <aside class="stack">
        <div class="card">
            <h3>What you get</h3>
            <ol class="steps">
                <li><span><b>Vendor bookings</b><br><small>Online applications, three ways to take payment, a waitlist, and one-tap entry for vendors who text you.</small></span></li>
                <li><span><b>A floor plan</b><br><small>Drag tables into place, seat vendors, and email everyone their table number.</small></span></li>
                <li><span><b>Tickets and the door</b><br><small>QR tickets, camera check-in, and cash and card door sales on any phone.</small></span></li>
                <li><span><b>A spot in the directory</b><br><small>Your events show up for shoppers across Connecticut.</small></span></li>
            </ol>
        </div>
    </aside>
</div>
@endsection
