@extends('layouts.base')

@section('topbar')
<header class="topbar">
    <div class="wrap">
        <a href="{{ route('directory.events') }}" aria-label="{{ config('platform.name') }} home"><x-logo /></a>
        <nav>
            <a href="{{ route('directory.events') }}" @class(['on' => request()->routeIs('directory.events')])>Events</a>
            <a href="{{ route('directory.vendors') }}" @class(['on' => request()->routeIs('directory.vendor*')])>Vendors</a>
            @if ($signedInVendor)
                <a href="{{ route('vendor.account') }}" @class(['on' => request()->routeIs('vendor.*')])>My vendor account</a>
            @else
                <a href="{{ route('vendor.login') }}" @class(['on' => request()->routeIs('vendor.*')])>Vendor sign in</a>
            @endif
            <a class="nav-cta" href="{{ route('signup') }}">Host an event</a>
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
@include('layouts.footer')
@endsection
