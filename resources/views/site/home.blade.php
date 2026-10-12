@extends('layouts.site')
@section('title', $organizer->name.' · '.config('platform.name'))

@section('content')
<div class="admin-head">
    <div>
        <h1>Upcoming events</h1>
        @if ($organizer->about)<p class="muted" style="max-width:62ch;margin:0">{{ $organizer->about }}</p>@endif
    </div>
</div>

<div class="grid grid-3">
    @forelse ($events as $event)
        @php $event->setRelation('organizer', $organizer); @endphp
        @include('partials.event-card', ['event' => $event])
    @empty
        <div class="card"><p style="margin:0">No upcoming events right now. Check back soon.</p></div>
    @endforelse
</div>

@if ($past->isNotEmpty())
    <section class="section">
        <div class="section-head"><h2>Past events</h2></div>
        <div class="grid grid-3" style="opacity:.75">
            @foreach ($past as $event)
                @php $event->setRelation('organizer', $organizer); @endphp
                @include('partials.event-card', ['event' => $event])
            @endforeach
        </div>
    </section>
@endif
@endsection
