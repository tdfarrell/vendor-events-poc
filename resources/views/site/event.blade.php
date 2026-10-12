@extends('layouts.site')
@section('title', $event->title.' · '.$organizer->name)
@section('meta')
    {{-- So a link pasted into a Facebook group shows a proper preview. --}}
    <meta name="description" content="{{ Str::limit($event->whenWhere(), 160) }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $event->title }}">
    <meta property="og:description" content="{{ $event->whenWhere() }}@if ($acceptingVendors && $spacesLeft) · {{ $spacesLeft }} vendor {{ $event->space(true) }} left @endif">
    <meta property="og:url" content="{{ $event->publicUrl() }}">
    <meta property="og:site_name" content="{{ $organizer->name }}">
@endsection

@section('before')
    @if ($preview)<div class="notice-banner rescheduled">Preview: this event is {{ $event->status }} and not visible to the public.</div>@endif
    @if ($event->notice)
        <div class="notice-banner {{ $event->notice }}">{{ $event->notice === 'cancelled' ? 'This event is cancelled.' : 'This event has been rescheduled.' }} {{ $event->notice_text }}</div>
    @endif
    <section class="hero">
        <div class="wrap">
            <div class="type-tag">{{ $event->typeLabel() }}</div>
            <h1>{{ $event->title }}</h1>
            <div class="when">{{ $event->starts_on?->format('l, F j, Y') ?? 'Date to be announced' }}@if ($event->hours) · {{ $event->hours }}@endif</div>
            @if ($event->venue_name)<div class="muted">{{ $event->venue_name }}@if ($event->venue_address), {{ $event->venue_address }}@endif</div>@endif
            <div class="row actions">
                @if ($tickets->isNotEmpty())<a class="btn primary big" href="{{ route('site.tickets', $event) }}">Buy tickets</a>@endif
                @if ($acceptingVendors)<a @class(['btn big', 'primary' => $tickets->isEmpty()]) href="{{ route('site.apply', $event) }}">Book a {{ $event->space() }}</a>@endif
                @if ($event->admission_note)<span class="badge brand" style="font-size:.85rem">{{ $event->admission_note }}</span>@endif
            </div>
        </div>
    </section>
@endsection

@section('content')
<div class="cols">
    <div class="stack">
        @if ($event->description)
            <div class="card"><h2>About</h2>{!! nl2br(e($event->description)) !!}</div>
        @endif
        <div class="card">
            <div class="row between"><h2 style="margin:0">Who's coming</h2><small>{{ $comingVendors->count() }} confirmed</small></div>
            @if ($comingVendors->isEmpty())
                <p class="muted" style="margin:12px 0 0">Vendors will appear here as they're confirmed.</p>
            @else
                <div class="grid grid-2" style="gap:4px;margin-top:10px">
                    @foreach ($comingVendors as $a)
                        @php $v = $a->vendor; $public = $v && $v->isPublic(); @endphp
                        @if ($public)
                            <a class="vendor-tile" href="{{ $v->profileUrl() }}">
                        @else
                            <div class="vendor-tile">
                        @endif
                            <span class="avatar">{{ $v?->initials() ?? Str::upper(Str::substr($a->business_name, 0, 2)) }}</span>
                            <span><strong>{{ $a->business_name }}</strong>@if ($a->table_numbers)<br><small>Table {{ $a->table_numbers }}</small>@elseif ($a->categories)<br><small>{{ Str::limit($a->categories, 40) }}</small>@endif</span>
                        {!! $public ? '</a>' : '</div>' !!}
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <aside class="stack">
        @if ($tickets->isNotEmpty())
            <div class="card">
                <h3>Tickets</h3>
                @foreach ($tickets as $t)
                    <div class="space-row"><span>{{ $t->name }}</span><strong>{{ $t->price_cents ? \App\Support\Money::format($t->price_cents) : 'Free' }}</strong></div>
                @endforeach
                <a class="btn primary block" style="margin-top:12px" href="{{ route('site.tickets', $event) }}">Buy tickets</a>
            </div>
        @endif
        @if ($acceptingVendors)
            <div class="card">
                <h3>Vendor {{ $event->space(true) }}</h3>
                @foreach ($spaces as $s)
                    <div class="space-row">
                        <span>{{ $s->name }}<br><small>{{ \App\Support\Money::format($s->price_cents) }}</small></span>
                        <span>@if ($s->available > 0)<span class="badge ok">{{ $s->available }} left</span>@else<span class="badge warn">Full · waitlist</span>@endif</span>
                    </div>
                @endforeach
                <a class="btn block" style="margin-top:12px" href="{{ route('site.apply', $event) }}">Book a {{ $event->space() }}</a>
            </div>
        @endif
        <div class="card">
            <h3>Details</h3>
            <dl class="kv">
                <dt>When</dt><dd>{{ $event->starts_on?->format('D, M j, Y') ?? 'TBA' }}@if ($event->hours)<br>{{ $event->hours }}@endif</dd>
                @if ($event->venue_name)<dt>Where</dt><dd>{{ $event->venue_name }}@if ($event->venue_address)<br>{{ $event->venue_address }}@endif</dd>@endif
                @if ($event->vendor_setup)<dt>Vendor setup</dt><dd>{{ $event->vendor_setup }}</dd>@endif
                <dt>Organizer</dt><dd>{{ $organizer->name }}</dd>
            </dl>
        </div>
    </aside>
</div>
@endsection
