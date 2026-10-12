@extends('layouts.directory')
@section('width', 'wrap-narrow')
@section('title', ($listing->exists ? 'Edit listing' : 'Add a listing').' · Platform admin')

@section('content')
<a class="crumb" href="{{ route('platform.dashboard') }}">← Platform admin</a>
<h1>{{ $listing->exists ? 'Edit listing' : 'Add a listing' }}</h1>
<p class="muted">For events run somewhere else. They show in the directory with a “claim this event” form for the organizer.</p>
<form method="post" action="{{ $listing->exists ? route('platform.listings.update', $listing) : route('platform.listings.store') }}" class="card lift">
    @csrf @if ($listing->exists) @method('put') @endif
    <div class="field"><label>Title</label><input type="text" name="title" required value="{{ old('title', $listing->title) }}"></div>
    <div class="fields-2">
        <div class="field"><label>Type</label><select name="type">@foreach (config('event_types') as $k => $t)<option value="{{ $k }}" @selected(old('type', $listing->type) === $k)>{{ $t['label'] }}</option>@endforeach</select></div>
        <div class="field"><label>Date</label><input type="date" name="starts_on" value="{{ old('starts_on', $listing->starts_on?->format('Y-m-d')) }}"></div>
    </div>
    <div class="fields-2">
        <div class="field"><label>Hours</label><input type="text" name="hours" value="{{ old('hours', $listing->hours) }}"></div>
        <div class="field"><label>Organizer</label><input type="text" name="host_name" value="{{ old('host_name', $listing->host_name) }}"></div>
    </div>
    <div class="field"><label>Venue</label><input type="text" name="venue_name" value="{{ old('venue_name', $listing->venue_name) }}"></div>
    <div class="fields-2">
        <div class="field"><label>Street address</label><input type="text" name="venue_address" value="{{ old('venue_address', $listing->venue_address) }}"></div>
        <div class="field"><label>Town</label><input type="text" name="town" value="{{ old('town', $listing->town) }}"></div>
    </div>
    <div class="field"><label>Their page or Facebook event <span class="hint">optional</span></label><input type="url" name="source_url" value="{{ old('source_url', $listing->source_url) }}"></div>
    <div class="field"><label>Description</label><textarea name="description">{{ old('description', $listing->description) }}</textarea></div>
    <div class="field"><label>Status</label><select name="status">@foreach (['listed' => 'Listed', 'claimed' => 'Claimed (hidden)', 'hidden' => 'Hidden'] as $k => $l)<option value="{{ $k }}" @selected(old('status', $listing->status) === $k)>{{ $l }}</option>@endforeach</select></div>
    <button class="btn fire">Save listing</button>
</form>
@endsection
