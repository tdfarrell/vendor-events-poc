@extends('layouts.admin')
@section('page', 'Order · '.$order->buyer_name)

@section('content')
<p><a href="{{ route('admin.events.show', [$order->event, 'tab' => 'tickets']) }}">← {{ $order->event->title }}</a></p>
<h1>{{ $order->buyer_name }}</h1>
<div class="cols">
    <div class="card flush">
        <table class="list">
            <thead><tr><th>Code</th><th>Type</th><th>Status</th></tr></thead>
            @foreach ($order->tickets as $t)
                <tr><td class="mono">{{ $t->displayCode() }}</td><td>{{ $t->ticketType->name }}</td>
                    <td>@if ($t->checked_in_at)<span class="badge ok">In {{ $t->checked_in_at->format('g:i A') }}</span>@else<span class="badge">{{ $t->status }}</span>@endif</td></tr>
            @endforeach
        </table>
    </div>
    <div class="card">
        <dl class="kv">
            <dt>Email</dt><dd>{{ $order->email }}</dd>
            <dt>Status</dt><dd>{{ $order->status }}</dd>
            <dt>Paid via</dt><dd>{{ $order->payment_method }}</dd>
            <dt>Subtotal</dt><dd>{{ \App\Support\Money::format($order->subtotal_cents) }}</dd>
            <dt>Service fee</dt><dd>{{ \App\Support\Money::format($order->fee_cents) }}</dd>
            <dt>Total</dt><dd>{{ \App\Support\Money::format($order->total_cents) }}</dd>
            <dt>When</dt><dd>{{ $order->paid_at?->format('M j, Y g:i A') }}</dd>
        </dl>
        <p style="margin-top:12px"><a href="{{ route('site.order', $order) }}" target="_blank">Buyer's ticket page ↗</a></p>
    </div>
</div>
@endsection
