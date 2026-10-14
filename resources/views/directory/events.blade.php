@extends('layouts.directory')

@section('title', config('platform.name').' · Vendor events across Connecticut')
@section('meta')
    <meta name="description" content="Card shows, craft fairs, flea markets, farmers markets and more across Connecticut, all in one place.">
    <meta property="og:title" content="{{ config('platform.name') }}: vendor events across Connecticut">
    <meta property="og:description" content="Card shows, craft fairs, flea markets, farmers markets and more, all in one place.">
@endsection
@section('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endsection

@php
    $qs = fn (array $change) => route('directory.events', array_filter(array_merge($f, $change), fn ($v) => $v !== '' && $v !== false && $v !== null));
@endphp

@section('before')
<section class="dir-hero">
    <div class="wrap">
        <h1>Find your people this weekend</h1>
        <p class="lead">Card shows, craft fairs, flea markets and food festivals across Connecticut, with the vendors who'll be there.</p>
        <form class="searchbar" method="get" action="{{ route('directory.events') }}" role="search">
            <div class="sf">
                <label for="q">What</label>
                <input id="q" name="q" type="search" value="{{ $f['q'] }}" placeholder="Pokémon, pottery, vintage…">
            </div>
            <div class="sf">
                <label for="town">Where</label>
                <input id="town" name="town" type="text" list="towns" value="{{ $f['town'] }}" placeholder="Any town">
                <datalist id="towns">@foreach ($towns as $t)<option value="{{ $t }}">@endforeach</datalist>
            </div>
            <div class="sf">
                <label for="when">When</label>
                <select id="when" name="when">
                    <option value="">Any date</option>
                    <option value="today" @selected($f['when'] === 'today')>Today</option>
                    <option value="weekend" @selected($f['when'] === 'weekend')>This weekend</option>
                    <option value="month" @selected($f['when'] === 'month')>Next 30 days</option>
                </select>
            </div>
            @if ($f['type'])<input type="hidden" name="type" value="{{ $f['type'] }}">@endif
            <button class="btn fire">Search</button>
        </form>
    </div>
</section>
@endsection

@section('content')
<nav class="cat-rail" aria-label="Browse by category">
    @foreach (config('event_types') as $key => $t)
        @php $n = ($counts[$key] ?? 0) + ($listingCounts[$key] ?? 0); @endphp
        <a href="{{ $qs(['type' => $f['type'] === $key ? '' : $key]) }}" @class(['cat', 'on' => $f['type'] === $key])
           style="background: linear-gradient(140deg, {{ $t['colors'][0] }}, {{ $t['colors'][1] }})">
            <span class="e" aria-hidden="true">{{ $t['emoji'] }}</span>
            <span><b>{{ $t['plural'] }}</b><br><small>{{ $n }} upcoming</small></span>
        </a>
    @endforeach
</nav>

@if ($weekend->isNotEmpty())
    <section class="section">
        <div class="section-head"><h2>Happening this weekend</h2><a href="{{ $qs(['when' => 'weekend']) }}">See all</a></div>
        <div class="strip">
            @foreach ($weekend as $event)
                @include('partials.event-card', ['event' => $event, 'faces' => $faces[$event->id] ?? collect()])
            @endforeach
        </div>
    </section>
@endif

<section class="section" id="browse">
    <div class="section-head">
        <h2>{{ $filtered ? 'Matching events' : 'Everything coming up' }}</h2>
        <span class="muted">{{ $items->count() }} {{ Str::plural('event', $items->count()) }}</span>
    </div>
    <div class="filter-bar">
        <a href="{{ $qs(['when' => '']) }}" @class(['chip', 'on' => $f['when'] === ''])>Any date</a>
        <a href="{{ $qs(['when' => 'today']) }}" @class(['chip', 'on' => $f['when'] === 'today'])>Today</a>
        <a href="{{ $qs(['when' => 'weekend']) }}" @class(['chip', 'on' => $f['when'] === 'weekend'])>This weekend</a>
        <a href="{{ $qs(['when' => 'month']) }}" @class(['chip', 'on' => $f['when'] === 'month'])>Next 30 days</a>
        <span class="sep" aria-hidden="true"></span>
        <a href="{{ $qs(['booking' => ! $f['booking']]) }}" @class(['chip', 'on' => $f['booking']])>Booking vendors</a>
        <a href="{{ $qs(['tickets' => ! $f['tickets']]) }}" @class(['chip', 'on' => $f['tickets']])>Tickets online</a>
        @if ($filtered)<a href="{{ route('directory.events') }}#browse" class="chip">Clear all</a>@endif
        <button type="button" class="chip map-toggle" id="mapToggle">Show map</button>
    </div>

    <div class="browse" id="browseWrap">
        <div class="browse-list">
            @forelse ($items as $item)
                @include('partials.event-row', ['item' => $item])
            @empty
                <div class="card" style="text-align:center;padding:40px">
                    <h3>Nothing matches yet</h3>
                    <p class="muted">Try another town or date, or look at everything coming up.</p>
                    <a class="btn" href="{{ route('directory.events') }}">See all events</a>
                </div>
            @endforelse
        </div>
        <div class="map-pane"><div id="dirMap" role="region" aria-label="Map of events"></div></div>
    </div>
</section>

@if ($spotlight->isNotEmpty())
    <section class="section">
        <div class="section-head"><h2>Vendors on the circuit</h2><a href="{{ route('directory.vendors') }}">All vendors</a></div>
        <div class="strip" style="grid-auto-columns:minmax(170px,1fr)">
            @foreach ($spotlight as $v)
                <a class="vendor-spot" href="{{ route('directory.vendor', $v->slug) }}">
                    <x-avatar :name="$v->business_name" :src="$v->logoUrl()" size="lg" />
                    <b>{{ $v->business_name }}</b>
                    <small>{{ $v->upcoming }} upcoming {{ Str::plural('event', $v->upcoming) }}@if ($v->town) · {{ $v->town }}@endif</small>
                </a>
            @endforeach
        </div>
    </section>
@endif

@if ($venues->isNotEmpty())
    <section class="section">
        <div class="section-head"><h2>Rooms to rent for your own party</h2><a href="{{ route('venues.index') }}">All venues</a></div>
        <div class="grid grid-2">
            @foreach ($venues as $venue)
                @include('partials.venue-row', ['venue' => $venue])
            @endforeach
        </div>
    </section>
@endif

<section class="section">
    <div class="cta-band">
        <div>
            <h2>Running a show, fair or market?</h2>
            <p>Take vendor bookings, sell tickets, lay out your floor and run the door from your phone. Free for organizers.</p>
        </div>
        <a class="btn fire big" href="{{ route('signup') }}">Host your event</a>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script type="application/json" id="pins">@json($pins)</script>
<script>
(function () {
    var pins = JSON.parse(document.getElementById('pins').textContent);
    var el = document.getElementById('dirMap');
    if (!window.L || !el) return;
    var map = L.map(el, { scrollWheelZoom: false }).setView(@json(config('platform.map_center')), {{ (int) config('platform.map_zoom') }});
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
    var markers = {}, bounds = [];
    pins.forEach(function (p) {
        var icon = L.divIcon({ className: '', iconSize: [34, 34], iconAnchor: [17, 34], popupAnchor: [0, -30],
            html: '<div class="pin' + (p.listing ? ' listing' : '') + '" style="background:' + p.color + '"><span>' + p.emoji + '</span></div>' });
        var m = L.marker([p.lat, p.lng], { icon: icon }).addTo(map)
            .bindPopup('<b>' + esc(p.title) + '</b><br>' + esc(p.when) + '<br><span style="color:#7A7394">' + esc(p.where) + '</span><br><a href="' + p.url + '">' + (p.listing ? 'Details' : 'See event') + '</a>');
        markers[p.id] = m; bounds.push([p.lat, p.lng]);
    });
    if (bounds.length > 1) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 12 });
    else if (bounds.length === 1) map.setView(bounds[0], 12);
    // Hovering a row highlights its pin.
    document.querySelectorAll('[data-pin]').forEach(function (row) {
        row.addEventListener('mouseenter', function () { var m = markers[row.dataset.pin]; if (m) { m.openPopup(); } });
    });
    var t = document.getElementById('mapToggle'), wrap = document.getElementById('browseWrap');
    if (t) t.addEventListener('click', function () {
        wrap.classList.toggle('show-map');
        t.textContent = wrap.classList.contains('show-map') ? 'Hide map' : 'Show map';
        setTimeout(function () { map.invalidateSize(); if (bounds.length > 1) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 }); }, 50);
    });
})();
</script>
@endpush
