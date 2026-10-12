@extends('layouts.directory')
@section('title', 'Platform admin · '.config('platform.name'))

@section('content')
<div class="admin-head">
    <div><h1>Platform admin</h1><p class="muted" style="margin:0">Organizers, directory listings and claims.</p></div>
    <div class="row">
        <a class="btn fire" href="{{ route('platform.listings.create') }}">Add a listing</a>
        <form method="post" action="{{ route('platform.logout') }}">@csrf<button class="btn">Sign out</button></form>
    </div>
</div>

@if ($claims->isNotEmpty())
    <div class="card flush lift" style="margin-bottom:24px">
        <div class="card-head"><h2>Claims to review</h2><span class="badge pending">{{ $claims->count() }}</span></div>
        @foreach ($claims as $c)
            <div class="card-body row between" style="border-bottom:1px solid var(--line)">
                <div><strong>{{ $c->listing->title }}</strong><br>
                    <small>{{ $c->name }} · {{ $c->email }}@if ($c->phone) · {{ $c->phone }}@endif @if ($c->role) · {{ $c->role }}@endif</small>
                    @if ($c->note)<br><small>“{{ $c->note }}”</small>@endif</div>
                <form method="post" action="{{ route('platform.claims.decide', $c) }}" class="row">@csrf
                    <button class="btn small ok" name="decision" value="approve">Approve</button>
                    <button class="btn small" name="decision" value="reject">Reject</button>
                </form>
            </div>
        @endforeach
    </div>
@endif

<div class="cols">
    <div class="card flush">
        <div class="card-head"><h2>Organizers</h2><small>{{ $organizers->count() }}</small></div>
        <table class="list">
            <thead><tr><th>Organizer</th><th>Address</th><th class="num">Events</th><th>Status</th></tr></thead>
            @foreach ($organizers as $o)
                <tr>
                    <td><strong>{{ $o->name }}</strong><br><small>{{ $o->contact_email }}</small></td>
                    <td><a href="{{ $o->url('/') }}" target="_blank">{{ $o->slug }}.{{ config('platform.domain') }}</a></td>
                    <td class="num">{{ $eventCounts[$o->id] ?? 0 }}</td>
                    <td>
                        @if ($o->isActive())<span class="badge ok">Live</span>
                        @else
                            <details><summary class="badge warn" style="cursor:pointer">Set up address</summary>
                                <ol style="font-size:.86rem;padding-left:18px;margin:8px 0">
                                    <li>cPanel → Domains: add <b>{{ $o->slug }}.{{ config('platform.domain') }}</b> with the site's folder as its root.</li>
                                    <li>Bluehost DNS: add an A record <b>{{ $o->slug }}</b> → the same IP as {{ config('platform.domain') }}.</li>
                                    <li>cPanel → SSL/TLS Status: run AutoSSL.</li>
                                </ol>
                                <form method="post" action="{{ route('platform.organizers.activate', $o->id) }}">@csrf<button class="btn small ok">Mark live and email them</button></form>
                            </details>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="card flush">
        <div class="card-head"><h2>Off-platform listings</h2><small>{{ $listings->count() }}</small></div>
        @forelse ($listings as $l)
            <a class="card-body row between" style="border-bottom:1px solid var(--line);text-decoration:none;color:inherit" href="{{ route('platform.listings.edit', $l) }}">
                <span><strong>{{ $l->title }}</strong><br><small>{{ $l->starts_on?->format('M j, Y') ?? 'TBA' }} · {{ $l->town }}</small></span>
                <span class="badge {{ $l->status === 'listed' ? 'ok' : '' }}">{{ $l->status }}</span>
            </a>
        @empty
            <p class="card-body muted" style="margin:0">No listings yet.</p>
        @endforelse
    </div>
</div>
@endsection
