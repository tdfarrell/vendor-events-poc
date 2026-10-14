@extends('layouts.admin')
@section('page', 'Venue')

@section('content')
<div class="admin-head">
    <div>
        <h1>Venue</h1>
        <p class="muted" style="margin:0">Rooms you rent out, and the rental requests people send from your venue page.</p>
    </div>
    <div class="row">
        <a class="btn" href="{{ route('admin.inquiries') }}">All requests</a>
        <a class="btn fire" href="{{ route('admin.venues.create') }}">{{ $venues->isEmpty() ? 'List your venue' : 'Add another venue' }}</a>
    </div>
</div>

@if ($venues->isEmpty())
    <div class="card lift" style="text-align:center;padding:44px 24px">
        <div style="font-size:2.6rem" aria-hidden="true">🏛️</div>
        <h2>Do you rent out a hall or room?</h2>
        <p class="muted" style="max-width:52ch;margin:0 auto 18px">List it in the {{ config('platform.name') }} directory with photos, capacity and prices. People planning birthday parties, showers, fundraisers and fairs send you a request form instead of calling. It's free.</p>
        <a class="btn fire" href="{{ route('admin.venues.create') }}">List your venue</a>
    </div>
@else
    <div class="grid grid-2" style="margin-bottom:26px">
        @foreach ($venues as $v)
            <div class="card flush lift">
                <div style="aspect-ratio:21/9"><x-venue-art :venue="$v" /></div>
                <div class="card-body">
                    <div class="row between">
                        <h2 style="margin:0">{{ $v->name }}</h2>
                        @if ($v->status === 'hidden')<span class="badge draft">Hidden</span>@else<span class="badge ok">Listed</span>@endif
                    </div>
                    <p class="muted" style="margin:6px 0 12px">{{ $v->kindLabel() }} · {{ $v->town }} · {{ $v->rooms->count() }} {{ Str::plural('room', $v->rooms->count()) }}@if ($v->maxGuests()) · up to {{ number_format($v->maxGuests()) }} guests @endif</p>
                    <div class="row">
                        <a class="btn small" href="{{ route('admin.venues.edit', $v->id) }}">Edit venue and rooms</a>
                        <a class="btn small" href="{{ $v->url() }}" target="_blank">View page ↗</a>
                        @if ($v->new_count)<a class="badge pending" href="{{ route('admin.inquiries', ['status' => 'new']) }}">{{ $v->new_count }} new {{ Str::plural('request', $v->new_count) }}</a>@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card flush">
        <div class="card-head"><h2>Latest requests</h2><a href="{{ route('admin.inquiries') }}">See all</a></div>
        @include('admin.partials.inquiry-table', ['inquiries' => $inquiries])
    </div>
@endif
@endsection
