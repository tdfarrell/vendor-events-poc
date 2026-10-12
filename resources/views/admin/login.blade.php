@extends('layouts.site')
@section('width', 'wrap-narrow')
@section('title', 'Sign in · '.$organizer->name)

@section('content')
<div class="card" style="margin-top:24px;max-width:420px">
    <h1>Organizer sign in</h1>
    <form method="post" action="{{ route('admin.login') }}">
        @csrf
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autofocus value="{{ old('email') }}"></div>
        <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required></div>
        <label class="check" style="margin-bottom:14px"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
        <button class="btn primary block">Sign in</button>
    </form>
    @if (config('platform.demo_mode'))
        <div class="alert info" style="margin:16px 0 0">Demo login: <strong>owner@{{ $organizer->slug }}.test</strong> / <strong>password</strong></div>
    @endif
</div>
@endsection
