@extends('layouts.directory')

@section('title', $vendor->business_name.' · '.config('platform.name'))
@section('meta')
    <meta property="og:title" content="{{ $vendor->business_name }}">
    <meta property="og:description" content="{{ Str::limit($vendor->description ?: 'See where '.$vendor->business_name.' will be next.', 180) }}">
    @if ($vendor->logo_path)<meta property="og:image" content="{{ $vendor->logoUrl() }}">@endif
@endsection

@section('content')
<p><a href="{{ route('directory.vendors') }}">← All vendors</a></p>
<div class="cols">
    <div>
        <div class="row" style="gap:18px;margin-bottom:18px">
            <x-avatar :name="$vendor->business_name" :src="$vendor->logoUrl()" size="lg" />
            <div>
                <h1 style="margin:0">{{ $vendor->business_name }}</h1>
                @if ($vendor->town)<div class="muted">{{ $vendor->town }}, CT</div>@endif
            </div>
        </div>
        @if ($vendor->categoryList())
            <div class="chips" style="margin-bottom:18px">@foreach ($vendor->categoryList() as $c)<span class="chip">{{ $c }}</span>@endforeach</div>
        @endif
        @if ($vendor->description)<p style="font-size:1.08rem">{!! nl2br(e($vendor->description)) !!}</p>@endif
        @if ($vendor->links())
            <div class="row" style="margin-bottom:22px">@foreach ($vendor->links() as $label => $url)<a class="btn small" href="{{ $url }}" rel="nofollow noopener" target="_blank">{{ $label }}</a>@endforeach</div>
        @endif
        @if ($vendor->photos->isNotEmpty())
            <div class="gallery" data-lightbox>
                @foreach ($vendor->photos as $p)<a href="{{ $p->url() }}"><img src="{{ $p->url() }}" alt="Photo from {{ $vendor->business_name }}" loading="lazy"></a>@endforeach
            </div>
        @endif
    </div>

    <aside class="card lift">
        <h2>Where they'll be next</h2>
        @forelse ($appearances as $a)
            <a class="ev-row" style="padding:8px 0;grid-template-columns:96px 1fr;--brand: {{ $a->event->organizer->brand_color }}" href="{{ $a->event->publicUrl() }}">
                <div class="ev-art"><x-poster :event="$a->event" /></div>
                <div>
                    <span class="ev-when">{{ $a->event->starts_on?->format('D, M j') }}</span>
                    <h3>{{ $a->event->title }}</h3>
                    <div class="ev-where">{{ $a->event->town }}@if ($a->table_numbers) · Table {{ $a->table_numbers }}@endif</div>
                </div>
            </a>
        @empty
            <p class="muted">No upcoming events booked yet.</p>
        @endforelse
    </aside>
</div>
@include('partials.lightbox')
@endsection
