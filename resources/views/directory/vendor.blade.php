@extends('layouts.directory')

@section('title', $vendor->business_name.' · '.config('platform.name'))
@section('meta')
    <meta property="og:title" content="{{ $vendor->business_name }}">
    <meta property="og:description" content="{{ Str::limit($vendor->description ?: 'See where '.$vendor->business_name.' will be next.', 180) }}">
@endsection

@section('content')
<div class="cols">
    <div>
        <div class="row" style="gap:16px;margin-bottom:16px">
            <span class="avatar lg">{{ $vendor->initials() }}</span>
            <div>
                <h1 style="margin:0">{{ $vendor->business_name }}</h1>
                @if ($vendor->town)<div class="muted">{{ $vendor->town }}</div>@endif
            </div>
        </div>
        @if ($vendor->categoryList())
            <div class="chips" style="margin-bottom:16px">@foreach ($vendor->categoryList() as $c)<span class="chip">{{ $c }}</span>@endforeach</div>
        @endif
        @if ($vendor->description)<p>{!! nl2br(e($vendor->description)) !!}</p>@endif
        @if ($vendor->links())
            <div class="row">@foreach ($vendor->links() as $label => $url)<a class="btn small" href="{{ $url }}" rel="nofollow noopener" target="_blank">{{ $label }}</a>@endforeach</div>
        @endif
    </div>

    <aside class="card">
        <h2>Where they'll be next</h2>
        @forelse ($appearances as $a)
            <a class="event-card" href="{{ $a->event->publicUrl() }}" style="padding:10px 0;border-bottom:1px solid var(--line);--brand: {{ $a->event->organizer->brand_color }}">
                <x-datebox :date="$a->event->starts_on" />
                <div>
                    <h3 style="font-size:1rem">{{ $a->event->title }}</h3>
                    <div class="meta">{{ $a->event->town }}@if ($a->table_numbers) · Table {{ $a->table_numbers }}@endif</div>
                    <div class="org-line">{{ $a->event->organizer->name }}</div>
                </div>
            </a>
        @empty
            <p class="muted">No upcoming events booked yet.</p>
        @endforelse
    </aside>
</div>
@endsection
