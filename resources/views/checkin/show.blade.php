@extends('layouts.base')
@section('title', 'Door · '.$event->title)
@section('no-ribbon', true)

@php $M = fn ($c) => \App\Support\Money::format($c); @endphp

@section('topbar')
<header @class(['topbar', 'light' => $organizer->brandInk() === '#111111'])>
    <div class="wrap" style="min-height:52px">
        <span class="brand" style="font-size:1rem">{{ $event->title }}<small>Door check-in · {{ $event->starts_on?->format('D, M j') }}</small></span>
        <nav><a href="{{ route('checkin', ['token' => $token, 'tab' => $tab]) }}">Refresh</a></nav>
    </div>
</header>
@endsection

@section('body')
<div class="door">
    <div class="stats" style="margin:14px 0">
        <div class="stat"><div class="n">{{ $stats['attendance'] }}</div><div class="l">In the door</div></div>
        <div class="stat"><div class="n">{{ $stats['online_in'] }}/{{ $stats['online_issued'] }}</div><div class="l">Online tickets in</div></div>
        <div class="stat"><div class="n">{{ $stats['vendors_in'] }}/{{ $stats['vendors_paid'] }}</div><div class="l">Vendors in</div></div>
    </div>

    <nav class="tabs">
        <a href="{{ route('checkin', ['token' => $token, 'tab' => 'tickets']) }}" @class(['on' => $tab === 'tickets'])>Tickets</a>
        <a href="{{ route('checkin', ['token' => $token, 'tab' => 'door']) }}" @class(['on' => $tab === 'door'])>Door sales</a>
        <a href="{{ route('checkin', ['token' => $token, 'tab' => 'vendors']) }}" @class(['on' => $tab === 'vendors'])>Vendors</a>
    </nav>

    @include('components.flash')

    @if ($tab === 'tickets')
        @if ($scan = session('scan'))
            <div class="result {{ $scan['tone'] }}" role="status"><h2>{{ $scan['title'] }}</h2><div>{{ $scan['detail'] }}</div></div>
        @endif

        @if ($matches = session('matches'))
            <div class="card flush" style="margin-bottom:14px">
                <div class="card-head"><h3>Matching orders</h3></div>
                @foreach ($matches as $m)
                    <div class="card-body" style="border-bottom:1px solid var(--line)">
                        <strong>{{ $m['name'] }}</strong> <small>{{ $m['email'] }}</small>
                        <div class="stack" style="margin-top:8px">
                            @foreach ($m['tickets'] as $t)
                                <form method="post" action="{{ route('checkin.scan', $token) }}" class="row between">
                                    @csrf <input type="hidden" name="code" value="{{ $t['code'] }}">
                                    <span class="mono">{{ $t['display'] }}</span> <small>{{ $t['type'] }}</small>
                                    @if ($t['in'])<span class="badge ok">In</span>@else<button class="btn small primary">Check in</button>@endif
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('checkin.scan', $token) }}" class="card">
            @csrf
            <label for="code">Ticket code, or buyer name or email</label>
            <input id="code" class="code" name="code" type="text" autocomplete="off" autocapitalize="characters" autofocus placeholder="ABCD-EFGH">
            <button class="btn primary big block" style="margin-top:10px">Check in</button>
            <p class="muted" style="font-size:.84rem;margin:10px 0 0">Camera scanning comes in the full build. For now, type the code under the QR.</p>
        </form>
    @endif

    @if ($tab === 'door')
        @php $types = $ticketTypes->where('price_cents', '>', 0); @endphp
        <div class="card">
            <h3>Sell at the door</h3>
            @forelse ($types as $t)
                <p style="margin:12px 0 6px"><strong>{{ $t->name }}</strong> · {{ $M($t->price_cents) }}</p>
                <div class="door-buttons">
                    @foreach (['cash' => 'Cash', 'card' => 'Card'] as $method => $mLabel)
                        @foreach ([1, 2] as $q)
                            <form method="post" action="{{ route('checkin.door', $token) }}">
                                @csrf
                                <input type="hidden" name="kind" value="sale"><input type="hidden" name="ticket_type_id" value="{{ $t->id }}">
                                <input type="hidden" name="quantity" value="{{ $q }}"><input type="hidden" name="method" value="{{ $method }}">
                                <button @class(['btn block', 'primary' => $method === 'cash'])>{{ $q }} × {{ $mLabel }}<small>{{ $M($t->price_cents * $q) }}</small></button>
                            </form>
                        @endforeach
                    @endforeach
                </div>
            @empty
                <p class="muted">No paid ticket types. Use a custom sale below.</p>
            @endforelse

            <details style="margin-top:16px">
                <summary>Custom sale</summary>
                <form method="post" action="{{ route('checkin.door', $token) }}" style="margin-top:10px">
                    @csrf <input type="hidden" name="kind" value="sale">
                    <div class="fields-2">
                        <div class="field"><label>Price each</label><input type="text" name="amount" inputmode="decimal" placeholder="5.00"></div>
                        <div class="field"><label>How many</label><input type="number" name="quantity" value="1" min="1" max="50"></div>
                    </div>
                    <div class="field"><label>Paid by</label>
                        <select name="method"><option value="cash">Cash</option><option value="card">Card</option><option value="other">Other</option></select>
                    </div>
                    <button class="btn block">Record sale</button>
                </form>
            </details>
        </div>

        <div class="card" style="margin-top:14px">
            <div class="row between"><h3 style="margin:0">Free kids</h3><span class="muted">{{ $stats['kids'] }} so far</span></div>
            <div class="door-buttons" style="margin-top:10px">
                @foreach ([1, 2] as $q)
                    <form method="post" action="{{ route('checkin.door', $token) }}">@csrf
                        <input type="hidden" name="kind" value="kids"><input type="hidden" name="quantity" value="{{ $q }}">
                        <button class="btn block">+{{ $q }} kid{{ $q > 1 ? 's' : '' }}</button>
                    </form>
                @endforeach
            </div>
        </div>

        <div class="card" style="margin-top:14px">
            <h3>Door totals</h3>
            <dl class="kv">
                <dt>Sold at the door</dt><dd>{{ $stats['door_qty'] }}</dd>
                <dt>Cash</dt><dd><strong>{{ $M($stats['door_cash']) }}</strong></dd>
                <dt>Card</dt><dd>{{ $M($stats['door_card']) }}</dd>
                @if ($stats['door_other'])<dt>Other</dt><dd>{{ $M($stats['door_other']) }}</dd>@endif
            </dl>
            @if ($recentDoor->isNotEmpty())
                <hr>
                <small class="muted">Recent</small>
                @foreach ($recentDoor as $d)
                    <div class="row between" style="font-size:.9rem;padding:4px 0"><span>{{ $d->quantity }} × {{ $d->label }} · {{ $d->method }}</span><span>{{ $d->created_at->format('g:i A') }}</span></div>
                @endforeach
                <form method="post" action="{{ route('checkin.door.undo', $token) }}" style="margin-top:8px" onsubmit="return confirm('Remove the last door entry?')">@csrf<button class="btn small danger">Undo last entry</button></form>
            @endif
        </div>
    @endif

    @if ($tab === 'vendors')
        <form method="get" class="row" style="margin-bottom:12px">
            <input type="hidden" name="tab" value="vendors">
            <input type="search" name="v" value="{{ $vendorSearch }}" placeholder="Search vendor or table" style="flex:1">
            <button class="btn">Find</button>
        </form>
        <div class="card flush">
            @forelse ($vendors as $v)
                <form method="post" action="{{ route('checkin.vendor', [$token, $v->id]) }}" @class(['vendor-check', 'in' => $v->checked_in_at])>
                    @csrf <input type="hidden" name="v" value="{{ $vendorSearch }}">
                    <div style="flex:1">
                        <strong>{{ $v->business_name }}</strong><br>
                        <small>{{ $v->contact_name }}@if ($v->table_numbers) · Table {{ $v->table_numbers }}@endif @if ($v->needs_power) · needs power @endif</small>
                    </div>
                    @if ($v->checked_in_at)
                        <button class="btn small">Undo ✓ {{ $v->checked_in_at->format('g:i') }}</button>
                    @else
                        <button class="btn small primary">Check in</button>
                    @endif
                </form>
            @empty
                <p class="card-body muted">No confirmed vendors{{ $vendorSearch ? ' match that search' : ' yet' }}.</p>
            @endforelse
        </div>
    @endif
</div>
@endsection
