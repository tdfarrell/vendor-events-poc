@extends('layouts.site')
@section('title', $event->title.' · '.$organizer->name)
@section('body-class', 'has-mobile-cta')
@section('meta')
    {{-- So a link pasted into a Facebook group or a text shows a proper preview. --}}
    <meta name="description" content="{{ Str::limit($event->whenWhere(), 160) }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $event->title }}">
    <meta property="og:description" content="{{ $event->whenWhere() }}@if ($acceptingVendors && $spacesLeft) · {{ $spacesLeft }} vendor {{ $event->space(true) }} left @endif">
    <meta property="og:url" content="{{ $event->publicUrl() }}">
    <meta property="og:site_name" content="{{ $organizer->name }}">
    <meta property="og:image" content="{{ $event->shareImageUrl() }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
@endsection
@section('head')
    @if ($event->hasLocation())<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">@endif
@endsection

@php
    $M = fn ($c) => \App\Support\Money::format($c);
    $cfg = $event->typeConfig();
@endphp

@section('before')
    @if ($preview)<div class="notice-banner preview">Preview: this event is {{ $event->status }}, so only you can see it.</div>@endif
    @if ($event->notice)
        <div class="notice-banner {{ $event->notice }}">{{ $event->notice === 'cancelled' ? 'This event is cancelled.' : 'This event has been rescheduled.' }} {{ $event->notice_text }}</div>
    @endif
    <section class="ev-hero">
        <div class="wrap ev-hero-grid">
            <div class="ev-art"><x-poster :event="$event" /></div>
            <div>
                <span class="ev-type">{{ $cfg['emoji'] }} {{ $event->typeLabel() }}</span>
                <h1>{{ $event->title }}</h1>
                <div class="facts">
                    <div class="fact"><span class="ic">📅</span><div><b>{{ $event->starts_on?->format('l, F j, Y') ?? 'Date to be announced' }}</b><span>{{ $event->hours ?: 'Hours to be announced' }}</span></div></div>
                    @if ($event->venue_name || $event->town)
                        <div class="fact"><span class="ic">📍</span><div><b>{{ $event->venue_name ?: $event->town }}</b><span>{{ $event->fullAddress() }}</span></div></div>
                    @endif
                    <div class="fact"><span class="ic">🎟️</span><div>
                        <b>@if ($tickets->isNotEmpty() && $ticketFrom !== null){{ $ticketFrom ? 'Tickets from '.$M($ticketFrom) : 'Free tickets' }}@elseif ($event->admission_note){{ $event->admission_note }}@else Admission at the door @endif</b>
                        <span>@if ($tickets->isNotEmpty() && $event->admission_note){{ $event->admission_note }}@elseif ($tickets->isNotEmpty())Buy online and skip the line @else No ticket needed in advance @endif</span>
                    </div></div>
                    @if ($comingVendors->isNotEmpty())
                        <div class="fact"><span class="ic">🛍️</span><div><b>{{ $comingVendors->count() }} {{ Str::plural('vendor', $comingVendors->count()) }} coming</b>
                            <span class="av-stack" style="margin-top:4px">@foreach ($comingVendors->take(6) as $a)<x-avatar :name="$a->business_name" :src="$a->vendor?->logoUrl() ?? ''" size="sm" />@endforeach</span></div></div>
                    @endif
                </div>
                <div class="ev-actions">
                    @if ($tickets->isNotEmpty())<a class="btn primary big" href="{{ route('site.tickets', $event) }}">Get tickets</a>@endif
                    @if ($acceptingVendors)<a @class(['btn big', 'primary' => $tickets->isEmpty()]) href="{{ route('site.apply', $event) }}">Book a {{ $event->space() }}</a>@endif
                    @if ($hasMap)<a class="btn big" href="{{ route('site.map', $event) }}">Floor map</a>@endif
                </div>
                <div class="share-row">
                    <button type="button" class="btn small" id="shareBtn" data-url="{{ $event->publicUrl() }}" data-title="{{ $event->title }}">Share</button>
                    <a class="btn small" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($event->publicUrl()) }}" target="_blank" rel="noopener">Post to Facebook</a>
                    @if ($event->starts_on)
                        <a class="btn small" href="{{ $event->googleCalendarUrl() }}" target="_blank" rel="noopener">Google Calendar</a>
                        <a class="btn small" href="{{ route('site.calendar', $event) }}">Apple / Outlook</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection

@section('content')
<div class="cols">
    <div class="stack">
        @if ($event->description)
            <section class="card"><h2>About this event</h2><div style="font-size:1.05rem">{!! nl2br(e($event->description)) !!}</div></section>
        @endif

        @if ($event->amenityList())
            <div class="chips">@foreach ($event->amenityList() as [$icon, $label])<span class="chip">{{ $icon }} {{ $label }}</span>@endforeach</div>
        @endif

        <section class="card">
            <div class="row between" style="margin-bottom:10px"><h2 style="margin:0">Who's coming</h2><small>{{ $comingVendors->count() }} confirmed</small></div>
            @if ($comingVendors->isEmpty())
                <p class="muted" style="margin:0">Vendors appear here as they're confirmed.@if ($acceptingVendors) <a href="{{ route('site.apply', $event) }}">Be the first to book a {{ $event->space() }}.</a>@endif</p>
            @else
                <div class="vendor-grid">
                    @foreach ($comingVendors as $a)
                        @php $v = $a->vendor; $public = $v && $v->isPublic(); @endphp
                        <{{ $public ? 'a' : 'div' }} class="vendor-tile" @if ($public) href="{{ $v->profileUrl() }}" @endif>
                            <x-avatar :name="$a->business_name" :src="$v?->logoUrl() ?? ''" />
                            <span style="min-width:0"><strong>{{ $a->business_name }}</strong><br>
                                <small>@if ($a->table_numbers)Table {{ $a->table_numbers }}@elseif ($v?->categories ?: $a->categories){{ Str::limit($v?->categories ?: $a->categories, 34) }}@endif</small></span>
                        </{{ $public ? 'a' : 'div' }}>
                    @endforeach
                </div>
                @if ($hasMap)<p style="margin:14px 0 0"><a class="btn small" href="{{ route('site.map', $event) }}">Find them on the floor map</a></p>@endif
            @endif
        </section>

        @if ($event->photos->isNotEmpty())
            <section class="card">
                <h2>Photos</h2>
                <div class="gallery" data-lightbox>
                    @foreach ($event->photos as $p)<a href="{{ $p->url() }}"><img src="{{ $p->url() }}" alt="{{ $p->caption ?: 'Photo from '.$event->title }}" loading="lazy"></a>@endforeach
                </div>
            </section>
        @endif

        @if ($event->faqList())
            <section class="card faq">
                <h2>Good to know</h2>
                @foreach ($event->faqList() as $f)
                    <details><summary>{{ $f['q'] }}</summary><p>{!! nl2br(e($f['a'])) !!}</p></details>
                @endforeach
            </section>
        @endif
    </div>

    <aside class="stack">
        @if ($tickets->isNotEmpty())
            <div class="card lift">
                <h3>Tickets</h3>
                @foreach ($tickets as $t)
                    <div class="space-row"><span>{{ $t->name }}@if ($t->available <= 0)<br><span class="badge warn">Sold out</span>@endif</span><strong>{{ $t->price_cents ? $M($t->price_cents) : 'Free' }}</strong></div>
                @endforeach
                <a class="btn primary block" style="margin-top:14px" href="{{ route('site.tickets', $event) }}">Get tickets</a>
            </div>
        @endif

        @if ($acceptingVendors)
            <div class="card lift">
                <h3>Vendor {{ $event->space(true) }}</h3>
                @foreach ($spaces as $s)
                    <div class="space-row">
                        <span>{{ $s->name }}<br><small>{{ $M($s->price_cents) }}</small></span>
                        <span>@if ($s->available > 0)<span class="badge ok">{{ $s->available }} left</span>@else<span class="badge warn">Full · waitlist</span>@endif</span>
                    </div>
                @endforeach
                <a class="btn block" style="margin-top:14px" href="{{ route('site.apply', $event) }}">Book a {{ $event->space() }}</a>
            </div>
        @endif

        @if ($event->venue_name || $event->town)
            <div class="card">
                <h3>Getting there</h3>
                @if ($event->hasLocation())<div class="mini-map" id="evMap"></div>@endif
                <p style="margin-bottom:6px"><strong>{{ $event->venue_name }}</strong><br>{{ $event->fullAddress() }}</p>
                @if ($event->parking_info)<p class="muted" style="font-size:.92rem">🅿️ {{ $event->parking_info }}</p>@endif
                <a class="btn small" href="{{ $event->directionsUrl() }}" target="_blank" rel="noopener">Directions</a>
                @if ($event->vendor_setup)<p class="muted" style="font-size:.88rem;margin:12px 0 0">Vendor setup: {{ $event->vendor_setup }}</p>@endif
            </div>
        @endif

        <div class="card">
            <h3>Hosted by</h3>
            <div class="organizer-card">
                <span class="org-mark">@if ($organizer->logo_path)<img src="{{ $organizer->logoUrl() }}" alt="">@else{{ $organizer->initials() }}@endif</span>
                <div><strong>{{ $organizer->name }}</strong>@if ($organizer->town)<br><small>{{ $organizer->town }}, CT</small>@endif</div>
            </div>
            @if ($organizer->about)<p style="margin:12px 0 0;font-size:.94rem">{{ Str::limit($organizer->about, 240) }}</p>@endif
            <div class="row" style="margin-top:12px">
                <a class="btn small" href="{{ route('site.home') }}">More events</a>
                @if ($organizer->contact_email)<a class="btn small" href="mailto:{{ $organizer->contact_email }}">Contact</a>@endif
            </div>
        </div>
    </aside>
</div>

<div class="mobile-cta">
    @if ($tickets->isNotEmpty())<a class="btn primary" href="{{ route('site.tickets', $event) }}">Get tickets</a>@endif
    @if ($acceptingVendors)<a @class(['btn', 'primary' => $tickets->isEmpty()]) href="{{ route('site.apply', $event) }}">Book a {{ $event->space() }}</a>@endif
    @if ($tickets->isEmpty() && ! $acceptingVendors)<a class="btn primary" href="{{ $event->directionsUrl() }}">Directions</a>@endif
</div>
@include('partials.lightbox')
@endsection

@push('scripts')
<script>
(function () {
    var b = document.getElementById('shareBtn');
    if (b) b.addEventListener('click', function () {
        var url = b.dataset.url, title = b.dataset.title;
        if (navigator.share) { navigator.share({ title: title, url: url }).catch(function () {}); return; }
        navigator.clipboard.writeText(url).then(function () { b.textContent = 'Link copied'; setTimeout(function () { b.textContent = 'Share'; }, 2200); });
    });
})();
</script>
@if ($event->hasLocation())
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
(function () {
    var c = [{{ $event->lat }}, {{ $event->lng }}];
    var m = L.map('evMap', { scrollWheelZoom: false, zoomControl: false }).setView(c, 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(m);
    L.marker(c, { icon: L.divIcon({ className: '', iconSize: [34, 34], iconAnchor: [17, 34], html: '<div class="pin" style="background:{{ $cfg['colors'][0] }}"><span>{{ $cfg['emoji'] }}</span></div>' }) }).addTo(m);
})();
</script>
@endif
@endpush
