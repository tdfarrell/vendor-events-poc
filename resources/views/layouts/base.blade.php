@php
    $brand = $organizer?->brand_color ?: '#2f5d50';
    $brandInk = $organizer?->brandInk() ?? '#ffffff';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('platform.name'))</title>
    @if (config('platform.noindex'))<meta name="robots" content="noindex, nofollow">@endif
    @yield('meta')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body style="--brand: {{ $brand }}; --brand-ink: {{ $brandInk }};">
    @unless (View::hasSection('no-ribbon'))
        <div class="poc-ribbon">Proof of concept · payments are simulated · <a href="{{ \App\Models\Organizer::directoryUrl('/') }}">{{ config('platform.name') }} directory</a></div>
    @endunless
    @yield('topbar')
    @yield('body')
</body>
</html>
