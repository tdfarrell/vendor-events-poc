@extends('layouts.site')
@section('title', 'Floor map · '.$event->title)
@section('width', 'wrap-full')
@section('head')<link rel="stylesheet" href="{{ asset('css/floorplan.css') }}?v=2">@endsection

@section('before')
    @if ($preview)<div class="notice-banner preview">Preview: only you can see this floor map. Turn on “Show the map on the public event page” in the floor plan builder to post it.</div>@endif
    <section class="wrap" style="padding-top:26px">
        <p><a href="{{ route('site.event', $event) }}">← {{ $event->title }}</a></p>
        <h1 style="margin-bottom:4px">Floor map</h1>
        <p class="muted">{{ $event->whenWhere() }}. Tap a {{ $event->space() }} to see who's there.</p>
    </section>
@endsection

@section('content')
<div class="fp-shell is-public-shell">
    <div class="fp-stats" id="fpStats"></div>
    <div class="fp-bench is-public">
        <section class="fp-stage" aria-label="Floor map">
            <div class="fp-bar">
                <div class="fp-legend" id="fpLegend"></div>
                <div class="fp-zoom">
                    <button type="button" id="zOut" aria-label="Zoom out">−</button>
                    <button type="button" id="zFit" aria-label="Fit to width">Fit</button>
                    <button type="button" id="zIn" aria-label="Zoom in">+</button>
                </div>
            </div>
            <div class="fp-pick" id="fpPick">Tap a {{ $event->space() }} to see who's there.</div>
            <div class="fp-canvas is-public" id="fpCanvas"><svg id="fpMap" class="is-public-map" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Floor map. Tap a table to see who is there."></svg></div>
        </section>
        <aside class="fp-panel fp-side" id="fpSide" aria-label="Vendors"></aside>
    </div>
</div>
@endsection

@push('scripts')
<script type="application/json" id="fp-data">{!! json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
<script src="{{ asset('js/floorplan.js') }}?v=2"></script>
@endpush
