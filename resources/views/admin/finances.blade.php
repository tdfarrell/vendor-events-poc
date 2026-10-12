@extends('layouts.admin')
@section('page', 'Financials')

@php
    $M = fn ($c) => \App\Support\Money::format($c);
    $t = $totals;
    $max = max(1, $byMonth->max() ?? 1);
    $years = range((int) date('Y') + 1, (int) date('Y') - 2);
@endphp

@section('content')
<div class="admin-head">
    <div><h1>Financials</h1><p class="muted" style="margin:0">{{ $year ? 'Events in '.$year : 'All events' }}, net of platform fees.</p></div>
    <div class="row">
        <a href="{{ route('admin.finances') }}" @class(['chip', 'on' => ! $year])>All time</a>
        @foreach ($years as $y)<a href="{{ route('admin.finances', ['year' => $y]) }}" @class(['chip', 'on' => $year === $y])>{{ $y }}</a>@endforeach
        <a class="btn small" href="{{ route('admin.finances.csv', array_filter(['year' => $year])) }}">Download CSV</a>
    </div>
</div>

<div class="stats" style="margin-bottom:22px">
    <div class="stat"><div class="n">{{ $M($t['total']) }}</div><div class="l">Total to you</div></div>
    <div class="stat"><div class="n">{{ $M($t['payout']) }}</div><div class="l">Paid out online</div></div>
    <div class="stat"><div class="n">{{ $M($t['collected_in_person']) }}</div><div class="l">Collected in person</div></div>
    <div class="stat"><div class="n">{{ $t['vendors_paid'] }}</div><div class="l">Vendor bookings paid</div></div>
    <div class="stat"><div class="n">{{ $t['tickets_sold'] + $t['door_count'] }}</div><div class="l">Tickets and door sales</div></div>
</div>

@if ($byMonth->count() > 1)
    <div class="card" style="margin-bottom:22px">
        <h3>By month</h3>
        <div class="bars" role="img" aria-label="Revenue by month">
            @foreach ($byMonth as $ym => $v)
                <div class="bar"><b>{{ $M($v) }}</b><span style="height:{{ round($v / $max * 100) }}%"></span><small>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $ym)->format('M y') }}</small></div>
            @endforeach
        </div>
    </div>
@endif

<div class="card flush">
    <div class="table-wrap"><table class="list">
        <thead><tr>
            <th>Event</th><th class="num">Vendors</th><th class="num">Spaces online</th><th class="num">Platform fee</th>
            <th class="num">Tickets online</th><th class="num">Paid out</th><th class="num">In person</th><th class="num">Total</th>
        </tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr class="click">
                <td><a href="{{ route('admin.events.show', [$r['event'], 'tab' => 'money']) }}">{{ $r['event']->title }}</a><br><small>{{ $r['event']->starts_on?->format('M j, Y') ?? 'TBA' }}</small></td>
                <td class="num">{{ $r['vendors_paid'] }}</td>
                <td class="num">{{ $M($r['tables_online']) }}</td>
                <td class="num">−{{ $M($r['tables_fee']) }}</td>
                <td class="num">{{ $M($r['tickets_online']) }}</td>
                <td class="num">{{ $M($r['payout']) }}</td>
                <td class="num">{{ $M($r['collected_in_person']) }}</td>
                <td class="num"><strong>{{ $M($r['total']) }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">No events in this range.</td></tr>
        @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot><tr>
                <td>Total</td><td class="num">{{ $t['vendors_paid'] }}</td><td class="num">{{ $M($t['tables_online']) }}</td><td class="num">−{{ $M($t['tables_fee']) }}</td>
                <td class="num">{{ $M($t['tickets_online']) }}</td><td class="num">{{ $M($t['payout']) }}</td><td class="num">{{ $M($t['collected_in_person']) }}</td><td class="num">{{ $M($t['total']) }}</td>
            </tr></tfoot>
        @endif
    </table></div>
</div>
<p class="muted" style="font-size:.88rem;margin-top:12px">Ticket buyers also paid {{ $M($t['ticket_fees_paid_by_buyers']) }} in service fees, which go to {{ config('platform.name') }} and don't come out of your share. Refunds: {{ $M($t['tables_refunded']) }}.</p>
@endsection
