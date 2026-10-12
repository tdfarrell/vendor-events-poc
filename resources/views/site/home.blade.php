@extends('layouts.site')
@section('title', $organizer->name)

@section('content')
<h1>Upcoming events</h1>
@if ($organizer->tagline)<p class="muted">{{ $organizer->tagline }}</p>@endif

<div class="grid grid-2" style="margin-top:20px">
    @forelse ($events as $event)
        <a class="card event-card" href="{{ route('site.event', $event) }}">
            <x-datebox :date="$event->starts_on" />
            <div>
                <div class="type-tag">{{ $event->typeLabel() }}</div>
                <h3>{{ $event->title }}</h3>
                <div class="meta">{{ collect([$event->venue_name, $event->town])->filter()->join(', ') }}@if ($event->hours) · {{ $event->hours }}@endif</div>
                @if ($event->notice === 'cancelled')<span class="badge cancelled">Cancelled</span>@endif
            </div>
        </a>
    @empty
        <div class="card"><p style="margin:0">No upcoming events right now. Check back soon.</p></div>
    @endforelse
</div>
@endsection
