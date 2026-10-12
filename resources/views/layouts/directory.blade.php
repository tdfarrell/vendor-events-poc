@extends('layouts.base')

@section('topbar')
<header class="topbar" style="--brand:#1d3b33">
    <div class="wrap">
        <a class="brand" href="{{ route('directory.events') }}">{{ config('platform.name') }}<small>Vendor events across {{ config('platform.state') === 'CT' ? 'Connecticut' : config('platform.state') }}</small></a>
        <nav>
            <a href="{{ route('directory.events') }}" @class(['on' => request()->routeIs('directory.events')])>Events</a>
            <a href="{{ route('directory.vendors') }}" @class(['on' => request()->routeIs('directory.vendor*')])>Vendors</a>
            @if ($signedInVendor)
                <a href="{{ route('vendor.account') }}" @class(['on' => request()->routeIs('vendor.*')])>My vendor account</a>
            @else
                <a href="{{ route('vendor.login') }}" @class(['on' => request()->routeIs('vendor.*')])>Vendor sign in</a>
            @endif
        </nav>
    </div>
</header>
@endsection

@section('body')
<main>
    <div class="@yield('width', 'wrap')">
        @include('components.flash')
        @yield('content')
    </div>
</main>
<footer class="site"><div class="wrap">{{ config('platform.name') }} (working name) · Proof of concept</div></footer>
@endsection
