@extends('layouts.directory')
@section('width', 'wrap-narrow')
@section('title', 'Welcome to '.config('platform.name'))

@section('content')
<div class="card lift" style="margin-top:20px;text-align:center;padding:40px 28px">
    <x-logo :size="56" :word="false" :light="true" />
    <h1 style="margin-top:16px">You're in, {{ $org->name }}</h1>
    <p>Your site will be at <strong>{{ $org->url('/') }}</strong>. New addresses take a little while to go live; we'll email {{ $org->contact_email }} when yours is ready, usually within a day.</p>
    <p class="muted">Then sign in with your email and password and create your first event, or open the Venue tab to list a hall you rent out.</p>
    <a class="btn" href="{{ route('directory.events') }}">Browse events meanwhile</a>
</div>
@endsection
