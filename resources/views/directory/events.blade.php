@extends('layouts.directory')

@section('title', 'Vendor events in Connecticut · '.config('platform.name'))

@section('content')
<section class="dir-hero">
    <h1>What's on near you this season</h1>
    <p class="muted">Card shows, craft fairs, flea markets, farmers markets and more, from every organizer on {{ config('platform.name') }}.</p>
</section>

<form method="get" class="filters card">
    <div class="field">
        <label for="type">Type</label>
        <select id="type" name="type">
            <option value="">All event types</option>
            @foreach (config('event_types') as $key => $t)
                <option value="{{ $key }}" @selected($type === $key)>{{ $t['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="town">Town</label>
        <input id="town" name="town" type="text" list="towns" value="{{ $town }}" placeholder="Any town">
        <datalist id="towns">@foreach ($towns as $t)<option value="{{ $t }}">@endforeach</datalist>
    </div>
    <div class="field">
        <label for="when">When</label>
        <select id="when" name="when">
            <option value="">Any upcoming date</option>
            <option value="weekend" @selected($when === 'weekend')>This weekend</option>
            <option value="month" @selected($when === 'month')>Next 30 days</option>
        </select>
    </div>
    <div class="row">
        <button class="btn primary">Show events</button>
        @if ($type || $town || $when)<a href="{{ route('directory.events') }}">Clear</a>@endif
    </div>
</form>

<p class="muted">{{ $events->count() }} {{ Str::plural('event', $events->count()) }}</p>

<div class="grid grid-2">
    @forelse ($events as $event)
        <a class="card event-card" href="{{ $event->publicUrl() }}" style="--brand: {{ $event->organizer->brand_color }}">
            <x-datebox :date="$event->starts_on" />
            <div>
                <div class="type-tag">{{ $event->typeLabel() }}</div>
                <h3>{{ $event->title }}</h3>
                <div class="meta">{{ collect([$event->venue_name, $event->town])->filter()->join(', ') }}@if ($event->hours) · {{ $event->hours }}@endif</div>
                <div class="org-line">by {{ $event->organizer->name }}</div>
                <div class="row" style="margin-top:8px;gap:6px">
                    @if ($event->notice === 'cancelled')<span class="badge cancelled">Cancelled</span>@endif
                    @if ($event->notice === 'rescheduled')<span class="badge warn">Rescheduled</span>@endif
                    @if ($event->tickets_on_sale)<span class="badge brand">Tickets online</span>@endif
                    @if ($event->applications_open)<span class="badge ok">Booking vendors</span>@endif
                </div>
            </div>
        </a>
    @empty
        <div class="card"><p>No events match those filters yet.</p><a href="{{ route('directory.events') }}">See all events</a></div>
    @endforelse
</div>
@endsection
