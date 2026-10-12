@extends('layouts.site')
@section('title', 'Test checkout')

@section('content')
<div class="checkout-box card">
    <span class="test-tag">TEST CHECKOUT</span>
    <p class="muted" style="margin-top:10px">This stands in for Stripe while the platform has no payment account. No card is charged.</p>
    <div class="muted">{{ $label }}</div>
    <div class="amount">{{ \App\Support\Money::format($amount) }}</div>
    @if ($kind === 'booking')
        <p class="muted" style="font-size:.88rem">Platform fee kept from the organizer's payout: {{ \App\Support\Money::format($fee) }}</p>
    @elseif ($fee)
        <p class="muted" style="font-size:.88rem">Includes {{ \App\Support\Money::format($fee) }} service fee.</p>
    @endif
    <form method="post" action="{{ route('checkout.test', [$kind, $token]) }}" class="stack" style="margin-top:16px">
        @csrf
        <button name="action" value="pay" class="btn ok big block">Pay {{ \App\Support\Money::format($amount) }} (test)</button>
        <button name="action" value="cancel" class="btn block">Cancel and go back</button>
    </form>
</div>
@endsection
