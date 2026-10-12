@extends('layouts.directory')
@section('width', 'wrap-narrow')
@section('title', 'Platform admin')

@section('content')
<div class="card lift" style="margin-top:20px;max-width:420px">
    <h1>Platform admin</h1>
    <form method="post" action="{{ route('platform.login') }}">
        @csrf
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required value="{{ old('email') }}"></div>
        <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required></div>
        <button class="btn fire block">Sign in</button>
    </form>
    @if (config('platform.demo_mode'))<div class="alert info" style="margin:16px 0 0">Demo login: <strong>{{ 'admin@'.config('platform.domain') }}</strong> / <strong>password</strong></div>@endif
</div>
@endsection
