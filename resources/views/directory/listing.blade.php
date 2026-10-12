@extends('layouts.directory')
@section('title', $listing->title.' · '.config('platform.name'))
@section('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endsection

@section('content')
<p><a href="{{ route('directory.events') }}">← All events</a></p>
<div class="cols">
    <div>
        <span class="ev-type">{{ $listing->typeConfig()['emoji'] }} {{ $listing->typeLabel() }}</span>
        <h1>{{ $listing->title }}</h1>
        <div class="facts">
            <div class="fact"><span class="ic">📅</span><div><b>{{ $listing->starts_on?->format('l, F j, Y') ?? 'Date to be announced' }}</b><span>{{ $listing->hours }}</span></div></div>
            @if ($listing->venue_name || $listing->town)
                <div class="fact"><span class="ic">📍</span><div><b>{{ $listing->venue_name ?: $listing->town }}</b><span>{{ collect([$listing->venue_address, $listing->town])->filter()->join(', ') }}</span></div></div>
            @endif
            @if ($listing->host_name)<div class="fact"><span class="ic">🙋</span><div><b>{{ $listing->host_name }}</b><span>Organizer</span></div></div>@endif
        </div>
        @if ($listing->description)<div class="card">{!! nl2br(e($listing->description)) !!}</div>@endif
        <div class="alert info" style="margin-top:18px">This event isn't run on {{ config('platform.name') }}, so tickets and vendor spaces are handled by its organizer.
            @if ($listing->source_url) <a href="{{ $listing->source_url }}" rel="nofollow noopener" target="_blank">See their page ↗</a>@endif
        </div>
        @if ($listing->lat !== null)<div class="mini-map" id="lmap" style="height:260px"></div>@endif
    </div>
    <aside class="card lift">
        <h2>Is this your event?</h2>
        <p class="muted">Claim it to take vendor bookings, sell tickets and run the door with {{ config('platform.name') }}. It's free for organizers, and we'll help you set it up.</p>
        <form method="post" action="{{ route('listings.claim', $listing) }}">
            @csrf
            <input type="text" name="company_website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            <div class="field"><label>Your name</label><input type="text" name="name" required value="{{ old('name') }}"></div>
            <div class="field"><label>Email</label><input type="email" name="email" required value="{{ old('email') }}"></div>
            <div class="field"><label>Phone <span class="hint">optional</span></label><input type="tel" name="phone" value="{{ old('phone') }}"></div>
            <div class="field"><label>Your role <span class="hint">e.g. organizer, PTO chair</span></label><input type="text" name="role" value="{{ old('role') }}"></div>
            <div class="field"><label>Anything we should know? <span class="hint">optional</span></label><textarea name="note" style="min-height:70px">{{ old('note') }}</textarea></div>
            <button class="btn fire block">Claim this event</button>
        </form>
    </aside>
</div>
@endsection

@if ($listing->lat !== null)
@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
(function () {
    var m = L.map('lmap', { scrollWheelZoom: false }).setView([{{ $listing->lat }}, {{ $listing->lng }}], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(m);
    L.marker([{{ $listing->lat }}, {{ $listing->lng }}]).addTo(m);
})();
</script>
@endpush
@endif
