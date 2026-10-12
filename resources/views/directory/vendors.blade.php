@extends('layouts.directory')

@section('title', 'Vendors · '.config('platform.name'))

@section('content')
<div class="admin-head">
    <div>
        <h1>Vendors</h1>
        <p class="muted" style="margin:0">Follow the sellers you like from event to event.</p>
    </div>
    <form method="get" class="row" role="search">
        <input type="search" name="q" value="{{ $search }}" placeholder="Name, what they sell, or town" style="min-width:280px">
        <button class="btn night">Search</button>
    </form>
</div>

<div class="grid grid-3">
    @forelse ($vendors as $v)
        <a class="card lift vendor-tile" style="padding:16px" href="{{ route('directory.vendor', $v->slug) }}">
            <x-avatar :name="$v->business_name" :src="$v->logoUrl()" />
            <span style="min-width:0">
                <strong>{{ $v->business_name }}</strong><br>
                <small>{{ collect([$v->town, Str::limit($v->categories, 44)])->filter()->join(' · ') ?: 'Vendor' }}</small>
            </span>
        </a>
    @empty
        <div class="card"><p style="margin:0">No vendors match “{{ $search }}”.</p></div>
    @endforelse
</div>
@endsection
