@extends('layouts.directory')
@section('title', $venue->name.' · '.$venue->town.' · '.config('platform.name'))
@section('meta')
    <meta name="description" content="{{ Str::limit($venue->about ?: $venue->kindLabel().' in '.$venue->town.' with rooms to rent.', 160) }}">
    <meta property="og:title" content="{{ $venue->name }}, {{ $venue->town }}">
    <meta property="og:description" content="{{ $venue->kindLabel() }}@if ($venue->maxGuests()) for up to {{ number_format($venue->maxGuests()) }} guests @endif · ask about a date on {{ config('platform.name') }}">
    @if ($venue->cover_path)<meta property="og:image" content="{{ $venue->coverUrl() }}">@endif
@endsection
@section('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endsection

@php
    $max = $venue->maxGuests();
    $from = $venue->priceFrom();
    $kinds = config('venues.event_kinds');
@endphp

@section('content')
<p><a href="{{ route('venues.index') }}">← All venues</a></p>

<div class="venue-hero">
    <x-venue-art :venue="$venue" class="big" />
</div>

<div class="cols">
    <div>
        <span class="ev-type">{{ $venue->kindConfig()['emoji'] }} {{ $venue->kindLabel() }}</span>
        <h1>{{ $venue->name }}</h1>
        <div class="facts">
            <div class="fact"><span class="ic">📍</span><div><b>{{ $venue->town }}</b><span>{{ $venue->fullAddress() }}</span></div></div>
            <div class="fact"><span class="ic">👥</span><div><b>{{ $max ? 'Up to '.number_format($max).' guests' : 'Ask about capacity' }}</b><span>{{ $venue->rooms->count() }} {{ Str::plural('room', $venue->rooms->count()) }} to rent</span></div></div>
            <div class="fact"><span class="ic">💵</span><div><b>{{ $from ? 'From $'.number_format($from) : 'Ask for pricing' }}</b><span>Prices are set by the venue</span></div></div>
        </div>

        @if ($venue->about)<div class="card" style="margin-bottom:18px">{!! nl2br(e($venue->about)) !!}</div>@endif

        <h2 id="rooms">Rooms</h2>
        <div class="stack" style="margin-bottom:22px">
            @forelse ($venue->rooms as $room)
                <div class="card room-card">
                    <div class="row between" style="align-items:flex-start">
                        <div>
                            <h3 style="margin-bottom:4px">{{ $room->name }}</h3>
                            <span class="muted">{{ $room->capacityText() ?: 'Ask about capacity' }}</span>
                        </div>
                        <b class="room-price">{{ $room->priceText() }}</b>
                    </div>
                    @if ($room->description)<p style="margin:10px 0 0">{{ $room->description }}</p>@endif
                    @if ($room->amenities)
                        <div class="chips" style="margin-top:12px">
                            @foreach ($room->amenities as $a)
                                @php [$ic, $label] = config('venues.amenities.'.$a, ['', $a]); @endphp
                                <span class="chip">{{ $ic }} {{ $label }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <p class="muted">Rooms haven't been added yet. Ask about a date and the venue will tell you what's available.</p>
            @endforelse
        </div>

        @if ($venue->rules)
            <h2>House rules</h2>
            <div class="card" style="margin-bottom:22px">{!! nl2br(e($venue->rules)) !!}</div>
        @endif

        @if ($events->isNotEmpty())
            <h2>Coming up here</h2>
            <div class="stack" style="margin-bottom:22px">
                @foreach ($events as $e)
                    @include('partials.event-row', ['item' => ['kind' => 'event', 'model' => $e]])
                @endforeach
            </div>
        @endif

        <div class="card">
            <h3>Getting there</h3>
            @if ($venue->lat !== null)<div class="mini-map" id="vMap"></div>@endif
            <p style="margin-bottom:6px"><strong>{{ $venue->name }}</strong><br>{{ $venue->fullAddress() }}</p>
            @if ($venue->parking_info)<p class="muted" style="font-size:.92rem">🅿️ {{ $venue->parking_info }}</p>@endif
            <a class="btn small" href="{{ $venue->directionsUrl() }}" target="_blank" rel="noopener">Directions</a>
        </div>
    </div>

    <aside class="stack">
        <div class="card lift" id="ask">
            <h2>Ask about a date</h2>
            <p class="muted" style="font-size:.94rem">Your request goes straight to {{ $venue->isClaimed() ? $venue->organizer->name : $venue->name }}, and they reply to you by email.</p>
            <form method="post" action="{{ route('venues.inquire', $venue->slug) }}">
                @csrf
                <input type="text" name="company_website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="field"><label for="i-kind">What's the occasion?</label>
                    <select id="i-kind" name="event_kind">@foreach ($kinds as $k => $l)<option value="{{ $k }}" @selected(old('event_kind') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="fields-2">
                    <div class="field"><label for="i-date">Date</label><input id="i-date" type="date" name="event_date" min="{{ today()->toDateString() }}" value="{{ old('event_date') }}"></div>
                    <div class="field"><label for="i-guests">Guests</label><input id="i-guests" type="number" min="1" name="guests" value="{{ old('guests') }}" placeholder="About how many?"></div>
                </div>
                <label class="check" style="margin:-4px 0 12px"><input type="checkbox" name="date_flexible" value="1" @checked(old('date_flexible'))> My date is flexible</label>
                <div class="field"><label for="i-time">Time <span class="hint">optional</span></label><input id="i-time" type="text" name="time_range" value="{{ old('time_range') }}" placeholder="e.g. 2 – 6 PM"></div>
                @if ($venue->rooms->count() > 1)
                    <div class="field"><label for="i-room">Room</label>
                        <select id="i-room" name="venue_room_id"><option value="">Not sure yet</option>@foreach ($venue->rooms as $r)<option value="{{ $r->id }}" @selected((string) old('venue_room_id') === (string) $r->id)>{{ $r->name }}</option>@endforeach</select></div>
                @endif
                <div class="field"><label for="i-name">Your name</label><input id="i-name" type="text" name="name" required value="{{ old('name') }}"></div>
                <div class="fields-2">
                    <div class="field"><label for="i-email">Email</label><input id="i-email" type="email" name="email" required value="{{ old('email') }}"></div>
                    <div class="field"><label for="i-phone">Phone <span class="hint">optional</span></label><input id="i-phone" type="tel" name="phone" value="{{ old('phone') }}"></div>
                </div>
                <div class="field"><label for="i-budget">Budget <span class="hint">optional</span></label><input id="i-budget" type="text" name="budget" value="{{ old('budget') }}" placeholder="e.g. around $500"></div>
                <div class="field"><label for="i-msg">Anything else? <span class="hint">optional</span></label><textarea id="i-msg" name="message" style="min-height:80px" placeholder="Kitchen use, setup time, decorations…">{{ old('message') }}</textarea></div>
                <button class="btn fire block">Send request</button>
            </form>
            @if ($venue->phone || $venue->website)
                <p class="muted" style="font-size:.88rem;margin:14px 0 0">
                    @if ($venue->phone)Or call {{ $venue->phone }}@endif
                    @if ($venue->website) · <a href="{{ $venue->website }}" target="_blank" rel="noopener nofollow">Website ↗</a>@endif
                </p>
            @endif
        </div>

        @unless ($venue->isClaimed())
            <details class="card" id="claim">
                <summary><b>Do you manage {{ $venue->name }}?</b><br><small class="muted">Claim this free listing to add photos, rooms and prices, and get requests in your own inbox.</small></summary>
                <form method="post" action="{{ route('venues.claim', $venue->slug) }}" style="margin-top:14px">
                    @csrf
                    <input type="text" name="company_website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="field"><label for="c-name">Your name</label><input id="c-name" type="text" name="name" required></div>
                    <div class="field"><label for="c-email">Email</label><input id="c-email" type="email" name="email" required></div>
                    <div class="field"><label for="c-role">Your role <span class="hint">e.g. hall manager, post commander</span></label><input id="c-role" type="text" name="role"></div>
                    <div class="field"><label for="c-note">Anything we should know? <span class="hint">optional</span></label><textarea id="c-note" name="note" style="min-height:60px"></textarea></div>
                    <button class="btn block">Claim this venue</button>
                </form>
            </details>
        @endunless
    </aside>
</div>

<div class="mobile-cta"><a class="btn primary" href="#ask">Ask about a date</a></div>
@endsection

@if ($venue->lat !== null)
@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
(function () {
    if (!window.L) return;
    var m = L.map('vMap', { scrollWheelZoom: false }).setView([{{ $venue->lat }}, {{ $venue->lng }}], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(m);
    L.marker([{{ $venue->lat }}, {{ $venue->lng }}]).addTo(m);
})();
</script>
@endpush
@endif
