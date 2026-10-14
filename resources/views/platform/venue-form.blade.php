@extends('layouts.directory')
@section('width', 'wrap-narrow')
@section('title', ($venue->exists ? 'Edit venue' : 'Add a venue').' · Platform admin')

@section('content')
<a class="crumb" href="{{ route('platform.dashboard') }}">← Platform admin</a>
<h1>{{ $venue->exists ? 'Edit venue' : 'Add a venue' }}</h1>
<p class="muted">For halls, posts, churches, schools and clubs that aren't on {{ config('platform.name') }} yet. Rental requests go to the email below with a “claim your listing” link.</p>
<form method="post" action="{{ $venue->exists ? route('platform.venues.update', $venue) : route('platform.venues.store') }}" class="card lift">
    @csrf @if ($venue->exists) @method('put') @endif
    <div class="field"><label>Name</label><input type="text" name="name" required value="{{ old('name', $venue->name) }}"></div>
    <div class="fields-2">
        <div class="field"><label>Kind</label><select name="kind">@foreach (config('venues.kinds') as $k => $t)<option value="{{ $k }}" @selected(old('kind', $venue->kind) === $k)>{{ $t['emoji'] }} {{ $t['label'] }}</option>@endforeach</select></div>
        <div class="field"><label>Status</label><select name="status">@foreach (['listed' => 'Listed', 'hidden' => 'Hidden'] as $k => $l)<option value="{{ $k }}" @selected(old('status', $venue->status) === $k)>{{ $l }}</option>@endforeach</select></div>
    </div>
    <div class="fields-2">
        <div class="field"><label>Street address</label><input type="text" name="address" value="{{ old('address', $venue->address) }}"></div>
        <div class="field"><label>Town</label><input type="text" name="town" required value="{{ old('town', $venue->town) }}"></div>
    </div>
    <div class="fields-2">
        <div class="field"><label>Rental email <span class="hint">requests go here</span></label><input type="email" name="contact_email" value="{{ old('contact_email', $venue->contact_email) }}"></div>
        <div class="field"><label>Phone</label><input type="tel" name="phone" value="{{ old('phone', $venue->phone) }}"></div>
    </div>
    <div class="field"><label>Website <span class="hint">optional</span></label><input type="url" name="website" value="{{ old('website', $venue->website) }}"></div>
    <div class="field"><label>About</label><textarea name="about">{{ old('about', $venue->about) }}</textarea></div>
    <div class="field"><label>House rules <span class="hint">alcohol, catering, insurance, end time</span></label><textarea name="rules" style="min-height:70px">{{ old('rules', $venue->rules) }}</textarea></div>
    <h3 style="margin-top:8px">Main room</h3>
    <div class="fields-2">
        <div class="field"><label>Room name</label><input type="text" name="room_name" value="{{ old('room_name', $room?->name) }}" placeholder="Main hall"></div>
        <div class="field"><label>Price from ($ per event)</label><input type="number" min="0" name="room_price_from" value="{{ old('room_price_from', $room?->price_from) }}"></div>
    </div>
    <div class="fields-2">
        <div class="field"><label>Seated</label><input type="number" min="0" name="room_seated" value="{{ old('room_seated', $room?->seated) }}"></div>
        <div class="field"><label>Standing</label><input type="number" min="0" name="room_standing" value="{{ old('room_standing', $room?->standing) }}"></div>
    </div>
    <button class="btn fire">Save venue</button>
</form>
@endsection
