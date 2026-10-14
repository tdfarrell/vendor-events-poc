@extends('layouts.directory')

@section('title', 'Venues to rent across Connecticut · '.config('platform.name'))
@section('meta')
    <meta name="description" content="Halls, posts, churches, schools and clubs to rent for birthday parties, anniversaries, showers, fundraisers and fairs across Connecticut.">
    <meta property="og:title" content="Rooms to rent across Connecticut · {{ config('platform.name') }}">
    <meta property="og:description" content="See capacity, prices and amenities, and send a rental request without calling around.">
@endsection
@section('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endsection

@php
    $qs = fn (array $change) => route('venues.index', array_filter(array_merge($f, $change), fn ($v) => $v !== '' && $v !== 0 && $v !== null));
@endphp

@section('before')
<section class="dir-hero venues-hero">
    <div class="wrap">
        <h1>Find a room for your party</h1>
        <p class="lead">Halls, posts, churches, schools and clubs across Connecticut. See who fits your guest list and what it costs, then ask in one form instead of calling around.</p>
        <form class="searchbar" method="get" action="{{ route('venues.index') }}" role="search">
            <div class="sf">
                <label for="q">What</label>
                <input id="q" name="q" type="search" value="{{ $f['q'] }}" placeholder="Hall, pavilion, kitchen…">
            </div>
            <div class="sf">
                <label for="town">Where</label>
                <input id="town" name="town" type="text" list="towns" value="{{ $f['town'] }}" placeholder="Any town">
                <datalist id="towns">@foreach ($towns as $t)<option value="{{ $t }}">@endforeach</datalist>
            </div>
            <div class="sf">
                <label for="guests">Guests</label>
                <input id="guests" name="guests" type="number" min="1" inputmode="numeric" value="{{ $f['guests'] ?: '' }}" placeholder="How many?">
            </div>
            @if ($f['kind'])<input type="hidden" name="kind" value="{{ $f['kind'] }}">@endif
            @if ($f['amenity'])<input type="hidden" name="amenity" value="{{ $f['amenity'] }}">@endif
            <button class="btn fire">Search</button>
        </form>
    </div>
</section>
@endsection

@section('content')
<nav class="cat-rail venue-rail" aria-label="Browse by kind of venue">
    @foreach (config('venues.kinds') as $key => $t)
        <a href="{{ $qs(['kind' => $f['kind'] === $key ? '' : $key]) }}" @class(['cat', 'on' => $f['kind'] === $key])
           style="background: linear-gradient(140deg, {{ $t['colors'][0] }}, {{ $t['colors'][1] }})">
            <span class="e" aria-hidden="true">{{ $t['emoji'] }}</span>
            <span><b>{{ $t['plural'] }}</b><br><small>{{ $counts[$key] ?? 0 }} listed</small></span>
        </a>
    @endforeach
</nav>

<section class="section" id="browse">
    <div class="section-head">
        <h2>{{ $filtered ? 'Matching venues' : 'Venues to rent' }}</h2>
        <span class="muted">{{ $venues->count() }} {{ Str::plural('venue', $venues->count()) }}</span>
    </div>
    <div class="filter-bar">
        @foreach (['kitchen', 'bar', 'tables_chairs', 'dance_floor', 'accessible', 'outdoor_space'] as $a)
            @php [$ic, $label] = config('venues.amenities.'.$a); @endphp
            <a href="{{ $qs(['amenity' => $f['amenity'] === $a ? '' : $a]) }}" @class(['chip', 'on' => $f['amenity'] === $a])>{{ $ic }} {{ $label }}</a>
        @endforeach
        @if ($filtered)<a href="{{ route('venues.index') }}#browse" class="chip">Clear all</a>@endif
        <button type="button" class="chip map-toggle" id="mapToggle">Show map</button>
    </div>

    <div class="browse" id="browseWrap">
        <div class="browse-list">
            @forelse ($venues as $venue)
                @include('partials.venue-row', ['venue' => $venue])
            @empty
                <div class="card" style="text-align:center;padding:40px">
                    <h3>No venues match yet</h3>
                    <p class="muted">Try a nearby town or a smaller guest count.</p>
                    <a class="btn" href="{{ route('venues.index') }}">See all venues</a>
                </div>
            @endforelse
        </div>
        <div class="map-pane"><div id="dirMap" role="region" aria-label="Map of venues"></div></div>
    </div>
</section>

<section class="section">
    <div class="cta-band">
        <div>
            <h2>Have a hall to rent?</h2>
            <p>List your rooms with photos, capacity and prices, and get rental requests by email instead of phone tag. Free for venues.</p>
        </div>
        <a class="btn fire big" href="{{ route('signup') }}?venue=1">List your venue</a>
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
            html: '<div class="pin" style="background:' + p.color + '"><span>' + p.emoji + '</span></div>' });
        var m = L.marker([p.lat, p.lng], { icon: icon }).addTo(map)
            .bindPopup('<b>' + esc(p.title) + '</b><br>' + esc(p.when) + '<br><span style="color:#7A7394">' + esc(p.where) + '</span><br><a href="' + p.url + '">See rooms</a>');
        markers[p.id] = m; bounds.push([p.lat, p.lng]);
    });
    if (bounds.length > 1) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 12 });
    else if (bounds.length === 1) map.setView(bounds[0], 12);
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
