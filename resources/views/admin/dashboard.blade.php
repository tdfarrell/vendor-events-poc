@extends('layouts.admin')
@section('page', 'Events')

@php $M = fn ($c) => \App\Support\Money::format($c); @endphp

@section('content')
<div class="admin-head">
    <div>
        <h1>Your events</h1>
        <p class="muted" style="margin:0">{{ $organizer->url('/') }}</p>
    </div>
    <a class="btn fire" href="{{ route('admin.events.create') }}">New event</a>
</div>

<div class="stats" style="margin-bottom:26px">
    <div class="stat"><div class="n">{{ $upcoming->count() }}</div><div class="l">Upcoming events</div></div>
    <div class="stat"><div class="n">{{ $upcoming->sum('confirmed_count') }}</div><div class="l">Vendors confirmed</div></div>
    <div class="stat"><div class="n">{{ $needsReview->count() }}</div><div class="l">Waiting on you</div></div>
    <div class="stat"><div class="n">{{ $M($money['total']) }}</div><div class="l">Brought in this year</div></div>
</div>

@if ($needsReview->isNotEmpty())
    <div class="card flush lift" style="margin-bottom:26px">
        <div class="card-head"><h2>Needs your review</h2><span class="badge pending">{{ $needsReview->count() }}</span></div>
        <div class="table-wrap"><table class="list">
            @foreach ($needsReview as $a)
                @php [$cls, $label] = $a->statusBadge(); @endphp
                <tr class="click">
                    <td><a href="{{ route('admin.applications.show', $a->id) }}"><strong>{{ $a->business_name }}</strong></a><br><small>{{ $a->contact_name }}</small></td>
                    <td>{{ $a->event->title }}</td>
                    <td><span class="badge {{ $cls }}">{{ $label }}</span></td>
                    <td class="num"><small>{{ $a->created_at->diffForHumans() }}</small></td>
                </tr>
            @endforeach
        </table></div>
    </div>
@endif

<div class="stack">
    @forelse ($upcoming as $e)
        @include('admin.partials.event-line', ['e' => $e])
    @empty
        <div class="card" style="text-align:center;padding:44px">
            <h2>Create your first event</h2>
            <p class="muted">Pick the kind of event and we'll set up the usual tables, prices and categories.</p>
            <a class="btn fire" href="{{ route('admin.events.create') }}">New event</a>
        </div>
    @endforelse
</div>

@if ($past->isNotEmpty())
    <section class="section">
        <div class="section-head"><h2>Past and archived</h2></div>
        <div class="stack" style="opacity:.8">
            @foreach ($past as $e)
                @include('admin.partials.event-line', ['e' => $e])
            @endforeach
        </div>
    </section>
@endif
@endsection
