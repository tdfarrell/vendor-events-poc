@extends('layouts.directory')
@section('width', 'wrap-narrow')

@section('content')
<div class="card" style="margin-top:24px">
    <h1>Check your email</h1>
    <p>We sent a sign-in link to <strong>{{ $email }}</strong>. It works once and expires in 30 minutes.</p>
    @if ($devLink)
        <div class="alert info"><strong>Local testing:</strong> email isn't sent in this setup, so here's the link: <a href="{{ $devLink }}">sign in as {{ $email }}</a></div>
    @endif
</div>
@endsection
