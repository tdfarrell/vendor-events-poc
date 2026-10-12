@extends('layouts.base')

@section('title')@yield('page', 'Admin') · {{ $organizer->name }}@endsection

@section('topbar')
<header @class(['topbar', 'light' => $organizer->brandInk() === '#111111'])>
    <div class="wrap">
        <a class="brand" href="{{ route('admin.dashboard') }}">{{ $organizer->name }}<small>Organizer admin</small></a>
        <nav>
            <a href="{{ route('admin.dashboard') }}" @class(['on' => request()->routeIs('admin.dashboard', 'admin.events.*', 'admin.applications.*', 'admin.orders.*')])>Events</a>
            <a href="{{ route('admin.settings') }}" @class(['on' => request()->routeIs('admin.settings')])>Settings</a>
            <a href="{{ route('site.home') }}" target="_blank">View site ↗</a>
            <form method="post" action="{{ route('admin.logout') }}" style="display:inline">@csrf<button>Sign out</button></form>
        </nav>
    </div>
</header>
@endsection

@section('body')
<main>
    <div class="wrap">
        @include('components.flash')
        @yield('content')
    </div>
</main>
@endsection
