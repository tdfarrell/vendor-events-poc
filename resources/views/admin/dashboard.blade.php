@extends('layouts.admin')
@section('page', 'Events')

@section('content')
<div class="row between" style="margin-bottom:16px">
    <h1 style="margin:0">Events</h1>
    <a class="btn primary" href="{{ route('admin.events.create') }}">New event</a>
</div>

@if ($needsReview->isNotEmpty())
    <div class="card flush" style="margin-bottom:20px">
        <div class="card-head"><h2>Needs your review</h2><span class="badge pending">{{ $needsReview->count() }}</span></div>
        <div class="table-wrap"><table class="list">
            @foreach ($needsReview as $a)
                @php [$cls, $label] = $a->statusBadge(); @endphp
                <tr class="click">
                    <td><a href="{{ route('admin.applications.show', $a->id) }}">{{ $a->business_name }}</a><br><small>{{ $a->contact_name }}</small></td>
                    <td>{{ $a->event->title }}</td>
                    <td><span class="badge {{ $cls }}">{{ $label }}</span></td>
                    <td class="num"><small>{{ $a->created_at->diffForHumans() }}</small></td>
                </tr>
            @endforeach
        </table></div>
    </div>
@endif

<div class="card flush">
    <div class="table-wrap"><table class="list">
        <thead><tr><th>Event</th><th>Date</th><th>Status</th><th class="num">Vendors</th><th class="num">To review</th></tr></thead>
        <tbody>
        @forelse ($events as $e)
            <tr class="click">
                <td><a href="{{ route('admin.events.show', $e) }}"><strong>{{ $e->title }}</strong></a><br><small>{{ $e->typeLabel() }}@if ($e->town) · {{ $e->town }}@endif</small></td>
                <td>{{ $e->starts_on?->format('D, M j, Y') ?? 'TBA' }}</td>
                <td><span class="badge {{ $e->status }}">{{ \App\Models\Event::STATUSES[$e->status] }}</span>@if ($e->notice) <span class="badge bad">{{ ucfirst($e->notice) }}</span>@endif</td>
                <td class="num">{{ $e->confirmed_count }}</td>
                <td class="num">{{ $e->review_count ?: '' }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><p style="margin:0">No events yet. <a href="{{ route('admin.events.create') }}">Create your first one.</a></p></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
