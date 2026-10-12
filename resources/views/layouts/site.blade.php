@extends('layouts.base')

@section('topbar')
<header class="topbar">
    <div class="wrap" style="min-height:52px">
        <a href="{{ \App\Models\Organizer::directoryUrl('/') }}" aria-label="{{ config('platform.name') }} directory"><x-logo :size="26" /></a>
        <nav>
            <a href="{{ \App\Models\Organizer::directoryUrl('/') }}">All events</a>
            @if ($signedInVendor)
                <a href="{{ \App\Models\Organizer::directoryUrl('/vendor/account') }}">My vendor account</a>
            @endif
            @auth <a href="{{ route('admin.dashboard') }}">Organizer admin</a> @endauth
        </nav>
    </div>
</header>
<div @class(['org-bar', 'on-light' => $organizer->brandInk() === '#111111'])>
    <div class="wrap">
        <a class="org-name" href="{{ route('site.home') }}">
            <span class="org-mark">@if ($organizer->logo_path)<img src="{{ $organizer->logoUrl() }}" alt="">@else{{ $organizer->initials() }}@endif</span>
            <span>{{ $organizer->name }}@if ($organizer->tagline)<small>{{ $organizer->tagline }}</small>@endif</span>
        </a>
        <nav><a href="{{ route('site.home') }}">Upcoming events</a></nav>
    </div>
</div>
@endsection

@section('body')
@yield('before')
<main>
    <div class="@yield('width', 'wrap')">
        @include('components.flash')
        @yield('content')
    </div>
</main>
@include('layouts.footer')
@endsection
