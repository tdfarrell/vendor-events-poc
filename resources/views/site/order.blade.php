@extends('layouts.site')
@section('width', 'wrap-narrow')
@section('title', 'Your tickets · '.$event->title)

@section('content')
<p class="no-print"><a href="{{ route('site.event', $event) }}">← {{ $event->title }}</a></p>
@if ($order->isPaid())
    <h1>Your tickets</h1>
    <p>{{ $order->buyer_name }} · {{ $event->whenWhere() }}</p>
    <p class="muted no-print">Show these at the door. A copy was emailed to {{ $order->email }}.</p>
    <div class="stack" style="margin-top:16px">
        @foreach ($order->tickets as $t)
            <div class="ticket">
                <img src="{{ route('site.order.qr', [$order, $t->code]) }}" alt="QR code for ticket {{ $t->displayCode() }}">
                <div>
                    <div class="type-tag">{{ $t->ticketType->name }}</div>
                    <div class="code">{{ $t->displayCode() }}</div>
                    <div class="muted" style="margin-top:6px">{{ $event->title }}<br>{{ $event->starts_on?->format('D, M j, Y') }}</div>
                    @if ($t->checked_in_at)<span class="badge ok">Checked in {{ $t->checked_in_at->format('g:i A') }}</span>@endif
                </div>
            </div>
        @endforeach
    </div>
    <p style="margin-top:16px" class="no-print"><button class="btn" onclick="window.print()">Print tickets</button></p>
@else
    <div class="card">
        <h1>Order not completed</h1>
        <p>This order is {{ $order->status }}. No tickets were issued.</p>
        <a class="btn primary" href="{{ route('site.tickets', $event) }}">Try again</a>
    </div>
@endif
@endsection
