@extends('layouts.directory')
@section('width', 'wrap-narrow')
@section('title', 'Vendor sign in · '.config('platform.name'))

@section('content')
<div class="card" style="margin-top:24px">
    <h1>Vendor sign in</h1>
    <p>One account for every event on {{ config('platform.name') }}. Enter your email and we'll send you a sign-in link. No password needed.</p>
    <form method="post" action="{{ route('vendor.login') }}">
        @csrf
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}">
        </div>
        <button class="btn primary">Email me a sign-in link</button>
    </form>
</div>
@endsection
