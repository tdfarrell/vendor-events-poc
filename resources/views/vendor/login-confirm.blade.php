@extends('layouts.directory')
@section('width', 'wrap-narrow')

@section('content')
<div class="card" style="margin-top:24px">
    <h1>Sign in</h1>
    <form method="post" action="{{ route('vendor.login.confirm', $token) }}">
        @csrf
        <button class="btn primary big">Continue to my vendor account</button>
    </form>
</div>
@endsection
