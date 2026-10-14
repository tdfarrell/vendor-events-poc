@extends('layouts.base')

@section('title')@yield('page', 'Admin') · {{ $organizer->name }}@endsection

@section('topbar')
<header class="topbar">
    <div class="wrap">
        <a href="{{ route('admin.dashboard') }}" class="row" style="gap:12px">
            <x-logo :size="28" :word="false" />
            <span class="org-name" style="font-size:1.05rem"><span>{{ $organizer->name }}<small>Organizer admin</small></span></span>
        </a>
        <nav>
            <a href="{{ route('admin.dashboard') }}" @class(['on' => request()->routeIs('admin.dashboard', 'admin.events.*', 'admin.applications.*', 'admin.orders.*', 'admin.floor-plan*', 'admin.messages*')])>Events</a>
            <a href="{{ route('admin.finances') }}" @class(['on' => request()->routeIs('admin.finances*')])>Financials</a>
            <a href="{{ route('admin.venues.index') }}" @class(['on' => request()->routeIs('admin.venues.*', 'admin.rooms.*', 'admin.inquiries*')])>Venue{{ ($newInquiries ?? 0) > 0 ? ' ('.$newInquiries.')' : '' }}</a>
            <a href="{{ route('admin.emails') }}" @class(['on' => request()->routeIs('admin.emails')])>Email log</a>
            <a href="{{ route('admin.settings') }}" @class(['on' => request()->routeIs('admin.settings')])>Settings</a>
            <a href="{{ route('site.home') }}" target="_blank">View site ↗</a>
            <form method="post" action="{{ route('admin.logout') }}" style="display:inline">@csrf<button>Sign out</button></form>
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
@endsection
