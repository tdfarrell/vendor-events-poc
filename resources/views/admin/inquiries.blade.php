@extends('layouts.admin')
@section('page', 'Rental requests')

@section('content')
<a class="crumb" href="{{ route('admin.venues.index') }}">← Venue</a>
<div class="admin-head"><div><h1>Rental requests</h1><p class="muted" style="margin:0">Sent from your venue page. Reply here or from the email you got; either way it goes to the person who asked.</p></div></div>
<div class="filter-bar" style="margin-top:0">
    <a href="{{ route('admin.inquiries') }}" @class(['chip', 'on' => $status === ''])>All · {{ $counts->sum() }}</a>
    @foreach (config('venues.inquiry_statuses') as $k => [$label])
        <a href="{{ route('admin.inquiries', ['status' => $k]) }}" @class(['chip', 'on' => $status === $k])>{{ $label }} · {{ $counts[$k] ?? 0 }}</a>
    @endforeach
</div>
<div class="card flush">@include('admin.partials.inquiry-table', ['inquiries' => $inquiries])</div>
@if ($inquiries->hasPages())
    <div class="row between" style="margin-top:16px">
        @if ($inquiries->previousPageUrl())<a class="btn small" href="{{ $inquiries->previousPageUrl() }}">Newer</a>@else<span></span>@endif
        <span class="muted">Page {{ $inquiries->currentPage() }} of {{ $inquiries->lastPage() }}</span>
        @if ($inquiries->nextPageUrl())<a class="btn small" href="{{ $inquiries->nextPageUrl() }}">Older</a>@else<span></span>@endif
    </div>
@endif
@endsection
