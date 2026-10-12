@extends('layouts.directory')

@section('title', 'Vendors · '.config('platform.name'))

@section('content')
<section class="dir-hero">
    <h1>Vendors</h1>
    <p class="muted">Follow the sellers you like from event to event.</p>
</section>

<form method="get" class="row" style="margin:16px 0 24px">
    <input type="search" name="q" value="{{ $search }}" placeholder="Search by name, what they sell, or town" style="max-width:420px">
    <button class="btn">Search</button>
</form>

<div class="grid grid-3">
    @forelse ($vendors as $v)
        <a class="card vendor-tile" href="{{ route('directory.vendor', $v->slug) }}">
            <span class="avatar">{{ $v->initials() }}</span>
            <span>
                <strong>{{ $v->business_name }}</strong><br>
                <small>{{ collect([$v->town, Str::limit($v->categories, 48)])->filter()->join(' · ') ?: 'Vendor' }}</small>
            </span>
        </a>
    @empty
        <p>No vendors found.</p>
    @endforelse
</div>
@endsection
