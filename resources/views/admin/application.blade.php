@extends('layouts.admin')
@section('page', $app->business_name)

@php
    [$cls, $label] = $app->statusBadge();
    $M = fn ($c) => \App\Support\Money::format($c);
    $paid = $app->paid_at && ! $app->refunded_at;
@endphp

@section('content')
<p><a href="{{ route('admin.events.show', $app->event) }}">← {{ $app->event->title }}</a></p>
<div class="row between" style="margin-bottom:16px">
    <div>
        <h1 style="margin:0">{{ $app->business_name }}</h1>
        <span class="badge {{ $cls }}">{{ $label }}</span>
    </div>
</div>

<div class="cols">
    <div class="stack">
        <div class="card">
            <h2>Application</h2>
            <dl class="kv">
                <dt>Contact</dt><dd>{{ $app->contact_name }}</dd>
                <dt>Email</dt><dd>@if ($app->email)<a href="mailto:{{ $app->email }}">{{ $app->email }}</a>@else — @endif</dd>
                <dt>Phone</dt><dd>{{ $app->phone ?: '—' }}</dd>
                <dt>Space</dt><dd>{{ $app->tableSummary() }}</dd>
                <dt>Amount</dt><dd>{{ $M($app->amount_cents) }}@if ($app->paid_at) · paid {{ $app->paid_at->format('M j, g:i A') }} ({{ $app->payment_method }})@endif</dd>
                @if ($app->platform_fee_cents)<dt>Platform fee</dt><dd>{{ $M($app->platform_fee_cents) }}</dd>@endif
                @if ($app->refunded_at)<dt>Refunded</dt><dd>{{ $M($app->refunded_cents) }} on {{ $app->refunded_at->format('M j') }}</dd>@endif
                @if ($app->status === 'approved')<dt>Pay by</dt><dd>{{ $app->pay_by?->format('M j, g:i A') }}@if ($app->holdExpired()) <span class="badge overdue">passed</span>@endif</dd>@endif
                <dt>Sells</dt><dd>{{ $app->categories ?: '—' }}</dd>
                <dt>Power</dt><dd>{{ $app->needs_power ? 'Needs electricity' : 'No' }}</dd>
                @if ($app->social_link)<dt>Link</dt><dd>{{ $app->social_link }}</dd>@endif
                <dt>Applied</dt><dd>{{ $app->created_at->format('M j, Y g:i A') }}</dd>
            </dl>
            @if ($app->inventory_description)<hr><h3>Inventory</h3><p>{!! nl2br(e($app->inventory_description)) !!}</p>@endif
            @if ($app->vendor_notes)<h3>Notes from vendor</h3><p>{!! nl2br(e($app->vendor_notes)) !!}</p>@endif
        </div>

        @if ($history->isNotEmpty())
            <div class="card flush">
                <div class="card-head"><h3>Their other bookings with you</h3></div>
                <table class="list">
                    @foreach ($history as $h)
                        @php [$hc, $hl] = $h->statusBadge(); @endphp
                        <tr><td><a href="{{ route('admin.applications.show', $h->id) }}">{{ $h->event->title }}</a></td><td>{{ $h->event->starts_on?->format('M j, Y') }}</td><td><span class="badge {{ $hc }}">{{ $hl }}</span></td></tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>

    <div class="stack">
        <div class="card">
            <h3>Actions</h3>
            <div class="stack">
                @if (in_array($app->status, ['pending', 'waitlisted', 'declined', 'cancelled', 'incomplete']) || $app->holdExpired())
                    <form method="post" action="{{ route('admin.applications.act', $app->id) }}">@csrf
                        <button name="action" value="approve" class="btn primary block">{{ $app->paidAwaitingReview() ? 'Approve and confirm' : ($app->holdExpired() ? 'Re-send payment link' : 'Approve and send payment link') }}</button>
                    </form>
                @endif
                @if (! $app->paid_at && in_array($app->status, ['pending', 'approved', 'waitlisted', 'incomplete']))
                    <form method="post" action="{{ route('admin.applications.act', $app->id) }}" class="row" style="gap:6px">@csrf
                        <input type="hidden" name="action" value="mark_paid">
                        <select name="method" style="flex:1"><option value="cash">Cash</option><option value="other">Other (Venmo, check…)</option></select>
                        <button class="btn">Mark paid</button>
                    </form>
                @endif
                @if (in_array($app->status, ['pending', 'approved']) && ! $paid)
                    <form method="post" action="{{ route('admin.applications.act', $app->id) }}">@csrf<button name="action" value="waitlist" class="btn block">Waitlist</button></form>
                @endif
                @if (in_array($app->status, ['pending', 'approved', 'waitlisted']))
                    <form method="post" action="{{ route('admin.applications.act', $app->id) }}" onsubmit="return confirm('Decline this vendor?{{ $paid ? ' Their payment will be refunded.' : '' }}')">@csrf
                        <button name="action" value="decline" class="btn danger block">Decline{{ $paid && $app->payment_method === 'online' ? ' and refund' : '' }}</button>
                    </form>
                @endif
                @if ($app->status === 'paid')
                    <form method="post" action="{{ route('admin.applications.act', $app->id) }}" onsubmit="return confirm('Cancel this booking?')">@csrf
                        <input type="hidden" name="action" value="cancel">
                        @if ($app->payment_method === 'online')<label class="check" style="margin-bottom:8px"><input type="checkbox" name="refund" value="1" checked> Refund {{ $M($app->amount_cents) }}</label>@endif
                        <button class="btn danger block">Cancel booking</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="card">
            <h3>Table and notes</h3>
            <form method="post" action="{{ route('admin.applications.act', $app->id) }}">@csrf
                <input type="hidden" name="action" value="details">
                <div class="field"><label>Table number(s)</label><input type="text" name="table_numbers" value="{{ $app->table_numbers }}" placeholder="12, 13"></div>
                <div class="field"><label>Private note</label><textarea name="admin_note" style="min-height:70px">{{ $app->admin_note }}</textarea></div>
                <button class="btn block">Save</button>
            </form>
        </div>
        @if ($app->status !== 'checkout')
            <p class="muted" style="font-size:.86rem">Vendor's status page: <a href="{{ $app->statusUrl() }}" target="_blank">open</a></p>
        @endif
    </div>
</div>
@endsection
