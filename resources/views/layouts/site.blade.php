@extends('layouts.base')

@section('topbar')
<header @class(['topbar', 'light' => $organizer->brandInk() === '#111111'])>
    <div class="wrap">
        <a class="brand" href="{{ route('site.home') }}">{{ $organizer->name }}@if ($organizer->tagline)<small>{{ $organizer->tagline }}</small>@endif</a>
        <nav>
            <a href="{{ route('site.home') }}">Events</a>
            @auth <a href="{{ route('admin.dashboard') }}">Admin</a> @endauth
        </nav>
    </div>
</header>
@endsection

@section('body')
@yield('before')
<main>
    <div class="@yield('width', 'wrap')">
        @include('components.flash')
        @yield('content')
    </div>
</main>
<footer class="site">
    <div class="wrap row between">
        <span>{{ $organizer->name }}@if ($organizer->contact_email) · <a href="mailto:{{ $organizer->contact_email }}">{{ $organizer->contact_email }}</a>@endif</span>
        <span>Find more events on <a href="{{ \App\Models\Organizer::directoryUrl('/') }}">{{ config('platform.name') }}</a></span>
    </div>
</footer>
@endsection
