@extends('layouts.site')
@section('width', 'wrap-narrow')
@section('title', 'Your booking · '.$event->title)

@php
    [$cls, $label] = $app->statusBadge();
    $canPay = ($app->status === 'approved' && ! $app->holdExpired()) || (in_array($app->status, ['checkout', 'incomplete'], true) && ! $app->paid_at);
    $headline = match (true) {
        $app->status === 'paid' => "You're confirmed",
        $app->paidAwaitingReview() => 'Paid, waiting on review',
        $app->status === 'approved' && ! $app->holdExpired() => "You're approved: pay to confirm",
        $app->holdExpired() => 'Your payment window has passed',
        $app->status === 'pending' => 'Application received',
        $app->status === 'waitlisted' => "You're on the waitlist",
        in_array($app->status, ['checkout', 'incomplete']) => 'Payment not finished',
        $app->status === 'declined' => 'Not this time',
        default => 'Booking '.$app->status,
    };
@endphp

@section('content')
<p><a href="{{ route('site.event', $event) }}">← {{ $event->title }}</a></p>
<div class="card">
    <span class="badge {{ $cls }}">{{ $label }}</span>
    <h1 style="margin-top:10px">{{ $headline }}</h1>
    <dl class="kv" style="margin:16px 0">
        <dt>Business</dt><dd>{{ $app->business_name }}</dd>
        <dt>Event</dt><dd>{{ $event->whenWhere() }}</dd>
        <dt>Space</dt><dd>{{ $app->tableSummary() }}</dd>
        @if ($app->table_numbers)<dt>Table number</dt><dd><strong>{{ $app->table_numbers }}</strong></dd>@endif
        @if ($app->amount_cents !== null)<dt>Total</dt><dd>{{ \App\Support\Money::format($app->amount_cents) }}@if ($app->paid_at) · paid {{ $app->paid_at->format('M j') }}@endif</dd>@endif
        @if ($app->refunded_at)<dt>Refund</dt><dd>{{ \App\Support\Money::format($app->refunded_cents) }} refunded {{ $app->refunded_at->format('M j') }}</dd>@endif
        @if ($app->status === 'approved' && $app->pay_by && ! $app->holdExpired())<dt>Pay by</dt><dd>{{ $app->pay_by->format('l, F j \a\t g:i A') }}</dd>@endif
        @if ($app->status === 'paid' && $event->vendor_setup)<dt>Setup</dt><dd>{{ $event->vendor_setup }}</dd>@endif
    </dl>

    @if ($canPay)
        <form method="post" action="{{ route('site.booking.pay', $app) }}">@csrf<button class="btn primary big">Pay {{ \App\Support\Money::format($app->amount_cents) }}</button></form>
    @elseif ($app->holdExpired())
        <p>Contact {{ $organizer->name }}@if ($organizer->contact_email) at <a href="mailto:{{ $organizer->contact_email }}">{{ $organizer->contact_email }}</a>@endif to see if your spot is still available.</p>
    @endif
</div>

@if (! $signedInVendor)
    <p class="muted" style="margin-top:16px">Keep all your bookings in one place: <a href="{{ route('vendor.login') }}">sign in to your vendor account</a> with {{ $app->email }}.</p>
@endif
@endsection
