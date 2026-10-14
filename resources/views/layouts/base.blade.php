@php
    $brand = $organizer?->brand_color ?: '#FF6A2B';
    $brandInk = $organizer?->brandInk() ?? '#ffffff';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('platform.name'))</title>
    @if (config('platform.noindex'))<meta name="robots" content="noindex, nofollow">@endif
    <meta name="theme-color" content="#1D1533">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preload" href="{{ asset('fonts/bricolage-grotesque.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/figtree.woff2') }}" as="font" type="font/woff2" crossorigin>
    @yield('meta')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=3">
    @yield('head')
</head>
<body class="@yield('body-class')" style="--brand: {{ $brand }}; --brand-ink: {{ $brandInk }};">
    @if (config('platform.demo_mode') && ! View::hasSection('no-ribbon'))
        <div class="demo-ribbon">Demo site with sample data · payments are simulated</div>
    @endif
    @yield('topbar')
    @yield('body')
    @stack('scripts')
</body>
</html>
