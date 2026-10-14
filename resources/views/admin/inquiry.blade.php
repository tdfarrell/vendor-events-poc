@extends('layouts.admin')
@section('page', 'Request from '.$inquiry->name)

@php [$cls, $label] = $inquiry->statusBadge(); @endphp

@section('content')
<a class="crumb" href="{{ route('admin.inquiries') }}">← Rental requests</a>
<div class="admin-head">
    <div>
        <h1>{{ $inquiry->kindLabel() }} · {{ $inquiry->name }}</h1>
        <p class="muted" style="margin:0">{{ $inquiry->venue->name }}@if ($inquiry->room) · {{ $inquiry->room->name }}@endif · sent {{ $inquiry->created_at->format('M j, g:i A') }}</p>
    </div>
    <span class="badge {{ $cls }}" style="font-size:.9rem">{{ $label }}</span>
</div>

<div class="cols">
    <div class="stack">
        <div class="card">
            <div class="inq-meta">
                <div><b>Date</b>{{ $inquiry->event_date?->format('D, M j, Y') ?? 'Not set' }}@if ($inquiry->date_flexible) (flexible)@endif</div>
                <div><b>Time</b>{{ $inquiry->time_range ?: 'Not given' }}</div>
                <div><b>Guests</b>{{ $inquiry->guests ?? 'Not given' }}</div>
                <div><b>Budget</b>{{ $inquiry->budget ?: 'Not given' }}</div>
            </div>
            @if ($inquiry->message)<p style="margin:0;white-space:pre-line">{{ $inquiry->message }}</p>@else<p class="muted" style="margin:0">No message.</p>@endif
        </div>

        <form method="post" action="{{ route('admin.inquiries.act', $inquiry->id) }}" class="card lift">
            @csrf
            <h2>Reply</h2>
            <p class="muted" style="font-size:.92rem">Goes to {{ $inquiry->email }} from {{ $organizer->fromName() }}. Their reply comes back to {{ $inquiry->venue->inquiryEmail() }}.</p>
            <div class="field"><label for="reply" class="sr-only">Your reply</label>
                <textarea id="reply" name="reply" rows="7" placeholder="{{ $inquiry->event_date ? 'Good news, '.$inquiry->event_date->format('F j').' is open.' : 'Thanks for asking!' }} The {{ $inquiry->room?->name ?? 'hall' }} is $… for …">{{ old('reply') }}</textarea></div>
            <div class="row between">
                <div class="row">
                    <label for="status" class="muted" style="font-size:.9rem">Then mark as</label>
                    <select id="status" name="status" style="width:auto">
                        <option value="">Keep as is</option>
                        @foreach (config('venues.inquiry_statuses') as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
                <button class="btn fire">Send reply</button>
            </div>
        </form>

        @if ($sent->isNotEmpty())
            <div class="card flush">
                <div class="card-head"><h3>What you've sent them</h3></div>
                @foreach ($sent as $m)
                    <details style="border-bottom:1px solid var(--line)">
                        <summary class="card-body" style="cursor:pointer">{{ $m->subject }} <small class="muted">· {{ $m->created_at->format('M j, g:i A') }}</small></summary>
                        <div class="card-body" style="padding-top:0"><pre class="mail-body">{{ $m->body }}</pre></div>
                    </details>
                @endforeach
            </div>
        @endif
    </div>

    <aside class="stack">
        <div class="card">
            <h3>Contact</h3>
            <p style="margin:0"><strong>{{ $inquiry->name }}</strong><br>{{ $inquiry->email }}@if ($inquiry->phone)<br>{{ $inquiry->phone }}@endif</p>
        </div>
        <form method="post" action="{{ route('admin.inquiries.act', $inquiry->id) }}" class="card">
            @csrf
            <h3>Status</h3>
            <div class="chips">
                @foreach (config('venues.inquiry_statuses') as $k => [$l])
                    <button name="status" value="{{ $k }}" @class(['chip', 'on' => $inquiry->status === $k])>{{ $l }}</button>
                @endforeach
            </div>
        </form>
        @if ($others->isNotEmpty())
            <div class="card">
                <h3>Earlier requests from them</h3>
                @foreach ($others as $o)
                    <p style="margin:0 0 6px"><a href="{{ route('admin.inquiries.show', $o->id) }}">{{ $o->kindLabel() }}</a> <small class="muted">{{ $o->created_at->format('M j, Y') }}</small></p>
                @endforeach
            </div>
        @endif
    </aside>
</div>
@endsection
