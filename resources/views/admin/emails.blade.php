@extends('layouts.admin')
@section('page', 'Email log')

@php
    $kinds = ['' => 'All', 'booking' => 'Bookings', 'tickets' => 'Tickets', 'tables' => 'Table numbers', 'message' => 'Your messages', 'organizer' => 'Alerts to you'];
@endphp

@section('content')
<div class="admin-head"><div><h1>Email log</h1><p class="muted" style="margin:0">Every email sent in your name.@if (config('mail.default') === 'log') This demo doesn't deliver email yet, so this is the only place to read them.@endif</p></div></div>
<div class="filter-bar" style="margin-top:0">
    @foreach ($kinds as $k => $label)<a href="{{ route('admin.emails', array_filter(['kind' => $k])) }}" @class(['chip', 'on' => $kind === $k])>{{ $label }}</a>@endforeach
</div>
<div class="card flush">
    @forelse ($mails as $m)
        <details style="border-bottom:1px solid var(--line)">
            <summary class="card-body" style="cursor:pointer;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px">
                <span><strong>{{ $m->subject }}</strong><br><small>To {{ $m->to_email }}@if ($m->event_id && isset($events[$m->event_id])) · {{ $events[$m->event_id]->title }}@endif</small></span>
                <span class="num"><span class="badge {{ $m->status === 'sent' ? 'ok' : 'failed' }}">{{ $m->status }}</span><br><small>{{ $m->created_at->format('M j, g:i A') }}</small></span>
            </summary>
            <div class="card-body" style="padding-top:0"><pre class="mail-body">{{ $m->body }}</pre></div>
        </details>
    @empty
        <p class="card-body muted" style="margin:0">No email yet.</p>
    @endforelse
</div>
@if ($mails->hasPages())
    <div class="row between" style="margin-top:16px">
        @if ($mails->previousPageUrl())<a class="btn small" href="{{ $mails->previousPageUrl() }}">Newer</a>@else<span></span>@endif
        <span class="muted">Page {{ $mails->currentPage() }} of {{ $mails->lastPage() }}</span>
        @if ($mails->nextPageUrl())<a class="btn small" href="{{ $mails->nextPageUrl() }}">Older</a>@else<span></span>@endif
    </div>
@endif
@endsection
