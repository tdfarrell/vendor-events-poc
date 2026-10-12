@extends('layouts.admin')
@section('page', $event->title)

@php $M = fn ($c) => \App\Support\Money::format($c); @endphp

@section('content')
<a class="crumb" href="{{ route('admin.dashboard') }}">← Events</a>
<div class="ev-admin-card" style="grid-template-columns:150px minmax(0,1fr);border:0;padding:0;background:none;margin-bottom:10px">
    <div class="ev-art"><x-poster :event="$event" /></div>
    <div>
        <div class="row" style="gap:6px;margin-bottom:6px">
            <span class="badge {{ $event->status }}">{{ \App\Models\Event::STATUSES[$event->status] }}</span>
            <span class="badge">{{ $event->typeConfig()['emoji'] }} {{ $event->typeLabel() }}</span>
            @if ($event->notice)<span class="badge bad">{{ ucfirst($event->notice) }}</span>@endif
        </div>
        <h1 style="margin:0 0 4px;font-size:clamp(1.7rem,3vw,2.3rem)">{{ $event->title }}</h1>
        <div class="muted">{{ $event->whenWhere() }}</div>
    </div>
</div>
<div class="row" style="margin-bottom:6px">
    <a class="btn small" href="{{ route('admin.events.edit', $event) }}">Edit details</a>
    <a class="btn small" href="{{ route('admin.floor-plan', $event) }}">Floor plan @if ($unsentTables)<span class="badge warn">{{ $unsentTables }} to email</span>@endif</a>
    <a class="btn small" href="{{ route('admin.messages.create', $event) }}">Message vendors</a>
    <details class="no-print" style="display:inline-block;position:relative">
        <summary class="btn small" style="list-style:none">Duplicate</summary>
        <form method="post" action="{{ route('admin.events.duplicate', $event) }}" class="card lift" style="position:absolute;z-index:20;top:44px;left:0;width:290px">
            @csrf
            <p style="margin-top:0;font-size:.9rem">Copies details, {{ $event->space(true) }}, ticket types and the floor plan into a new draft. Vendors and sales stay here.</p>
            <div class="field"><label>Date of the new event</label><input type="date" name="starts_on" value="{{ $event->starts_on?->copy()->addMonth()->format('Y-m-d') }}"></div>
            <button class="btn fire block">Make a copy</button>
        </form>
    </details>
    <a class="btn small" href="{{ route('site.event', $event) }}" target="_blank">View page ↗</a>
</div>

<div class="stats" style="margin:18px 0">
    @php $totalSpaces = $spaces->sum('quantity'); $taken = $spaces->sum('committed'); @endphp
    <div class="stat"><div class="n">{{ $taken }}/{{ $totalSpaces }}</div><div class="l">{{ ucfirst($event->space(true)) }} taken or held</div>
        <div class="meter"><span style="width:{{ $totalSpaces ? round($taken / $totalSpaces * 100) : 0 }}%"></span></div></div>
    <div class="stat"><div class="n">{{ $applications->where('status', 'pending')->count() }}</div><div class="l">To review</div></div>
    <div class="stat"><div class="n">{{ $stats['online_issued'] }}</div><div class="l">Online tickets sold</div></div>
    <div class="stat"><div class="n">{{ $M($money['total']) }}</div><div class="l">Revenue to you</div></div>
</div>

<nav class="tabs">
    @foreach (['vendors' => 'Vendors', 'spaces' => ucfirst($event->space(true)), 'tickets' => 'Tickets', 'door' => 'Door', 'money' => 'Money'] as $k => $l)
        <a href="{{ route('admin.events.show', [$event, 'tab' => $k]) }}" @class(['on' => $tab === $k])>{{ $l }}</a>
    @endforeach
</nav>

@if ($tab === 'vendors')
    <div class="cols">
        <div class="card flush">
            <div class="card-head">
                <h2>Vendors</h2>
                <form method="get" class="row" style="gap:6px">
                    <input type="hidden" name="tab" value="vendors">
                    <select name="status" onchange="this.form.submit()" style="width:auto;padding:6px 10px">
                        @foreach (['active' => 'Active', 'pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Confirmed', 'waitlisted' => 'Waitlisted', 'declined' => 'Declined', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l)
                            <option value="{{ $k }}" @selected($statusFilter === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                    <a class="btn small" href="{{ route('admin.events.vendors-csv', $event) }}">CSV</a>
                </form>
            </div>
            <div class="table-wrap"><table class="list">
                <thead><tr><th>Vendor</th><th>Space</th><th>Status</th><th class="num">Amount</th></tr></thead>
                <tbody>
                @forelse ($applications as $a)
                    @php [$cls, $label] = $a->statusBadge(); @endphp
                    <tr class="click">
                        <td><a href="{{ route('admin.applications.show', $a->id) }}"><strong>{{ $a->business_name }}</strong></a><br><small>{{ $a->contact_name }}@if ($a->categories) · {{ Str::limit($a->categories, 40) }}@endif</small></td>
                        <td>{{ $a->tableSummary() }}@if ($a->table_numbers)<br><small>Table {{ $a->table_numbers }}</small>@endif</td>
                        <td><span class="badge {{ $cls }}">{{ $label }}</span>@if ($a->payment_method && $a->payment_method !== 'online')<br><small>{{ $a->payment_method }}</small>@endif</td>
                        <td class="num">{{ $M($a->amount_cents) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><p class="muted" style="margin:0">No vendors here yet.</p></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="stack">
            <div class="card">
                <h3>Add a vendor</h3>
                <p class="muted" style="font-size:.88rem">For vendors who booked by text, Facebook or in person.</p>
                <form method="post" action="{{ route('admin.events.walk-in', $event) }}">
                    @csrf
                    <div class="field"><label>Business name</label><input type="text" name="business_name" required></div>
                    <div class="fields-2">
                        <div class="field"><label>Contact</label><input type="text" name="contact_name"></div>
                        <div class="field"><label>Phone</label><input type="tel" name="phone"></div>
                    </div>
                    <div class="field"><label>Email <span class="hint">optional; links their vendor account</span></label><input type="email" name="email"></div>
                    <div class="fields-2">
                        <div class="field"><label>Space</label><select name="table_type_id">@foreach ($spaces as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->available }} left)</option>@endforeach</select></div>
                        <div class="field"><label>How many</label><input type="number" name="quantity" value="1" min="1" max="20"></div>
                    </div>
                    <div class="fields-2">
                        <div class="field"><label>Table #</label><input type="text" name="table_numbers"></div>
                        <div class="field"><label>Amount <span class="hint">blank = list price</span></label><input type="text" name="amount" inputmode="decimal"></div>
                    </div>
                    <div class="field"><label>Payment</label>
                        <select name="method"><option value="cash">Paid cash</option><option value="other">Paid another way (Venmo, check…)</option><option value="unpaid">Not paid yet (holds the space)</option></select>
                    </div>
                    <button class="btn primary block">Add vendor</button>
                </form>
            </div>
        </div>
    </div>
@endif

@if ($tab === 'spaces')
    <div class="card flush">
        <div class="card-head"><h2>{{ ucfirst($event->space()) }} types</h2><small>Committed counts include holds during checkout and unpaid approvals.</small></div>
        <div class="table-wrap"><table class="list">
            <thead><tr><th>Name</th><th>Price</th><th>Count</th><th>Max per vendor</th><th>Taken</th><th></th></tr></thead>
            <tbody>
            @foreach ($spaces as $s)
                @php $f = 'tt-'.$s->id; @endphp
                <tr>
                    <td><input form="{{ $f }}" type="text" name="name" value="{{ $s->name }}" required><input form="{{ $f }}" type="hidden" name="description" value="{{ $s->description }}"></td>
                    <td style="width:110px"><input form="{{ $f }}" type="text" name="price" value="{{ number_format($s->price_cents / 100, 2, '.', '') }}" required></td>
                    <td style="width:90px"><input form="{{ $f }}" type="number" name="quantity" value="{{ $s->quantity }}" min="0" required></td>
                    <td style="width:90px"><input form="{{ $f }}" type="number" name="max_per_vendor" value="{{ $s->max_per_vendor }}" min="1" required></td>
                    <td>{{ $s->committed }}</td>
                    <td><button form="{{ $f }}" class="btn small">Save</button></td>
                </tr>
            @endforeach
            <tr>
                <td><input form="tt-new" type="text" name="name" placeholder="New type, e.g. Corner booth" required></td>
                <td><input form="tt-new" type="text" name="price" placeholder="50" required></td>
                <td><input form="tt-new" type="number" name="quantity" value="10" min="0" required></td>
                <td><input form="tt-new" type="number" name="max_per_vendor" value="2" min="1" required></td>
                <td></td>
                <td><button form="tt-new" class="btn small primary">Add</button></td>
            </tr>
            </tbody>
        </table></div>
        @foreach ($spaces as $s)
            <form id="tt-{{ $s->id }}" method="post" action="{{ route('admin.table-types.update', $s) }}">@csrf @method('put')</form>
        @endforeach
        <form id="tt-new" method="post" action="{{ route('admin.events.table-types', $event) }}">@csrf</form>
    </div>
    <p style="margin-top:12px"><a class="btn" href="{{ route('admin.floor-plan', $event) }}">Lay these out in the floor plan builder</a></p>
@endif

@if ($tab === 'tickets')
    @unless ($event->tickets_on_sale)
        <div class="alert warn">Online ticket sales are off for this event. Turn them on in <a href="{{ route('admin.events.edit', $event) }}">Edit</a>.</div>
    @endunless
    <div class="card flush" style="margin-bottom:20px">
        <div class="card-head"><h2>Ticket types</h2></div>
        <div class="table-wrap"><table class="list">
            <thead><tr><th>Name</th><th>Price</th><th>Quantity</th><th>Max/order</th><th>Sold</th><th>On sale</th><th></th></tr></thead>
            <tbody>
            @foreach ($ticketTypes as $t)
                @php $f = 'tk-'.$t->id; @endphp
                <tr>
                    <td><input form="{{ $f }}" type="text" name="name" value="{{ $t->name }}" required><input form="{{ $f }}" type="hidden" name="description" value="{{ $t->description }}"></td>
                    <td style="width:100px"><input form="{{ $f }}" type="text" name="price" value="{{ number_format($t->price_cents / 100, 2, '.', '') }}" required></td>
                    <td style="width:100px"><input form="{{ $f }}" type="number" name="quantity" value="{{ $t->quantity }}" min="0" required></td>
                    <td style="width:80px"><input form="{{ $f }}" type="number" name="max_per_order" value="{{ $t->max_per_order }}" min="1" required></td>
                    <td>{{ $t->sold }}</td>
                    <td><input form="{{ $f }}" type="checkbox" name="active" value="1" @checked($t->active)></td>
                    <td><button form="{{ $f }}" class="btn small">Save</button></td>
                </tr>
            @endforeach
            <tr>
                <td><input form="tk-new" type="text" name="name" placeholder="New ticket type" required></td>
                <td><input form="tk-new" type="text" name="price" placeholder="5" required></td>
                <td><input form="tk-new" type="number" name="quantity" value="100" min="0" required></td>
                <td><input form="tk-new" type="number" name="max_per_order" value="10" min="1" required></td>
                <td colspan="2"></td>
                <td><button form="tk-new" class="btn small primary">Add</button></td>
            </tr>
            </tbody>
        </table></div>
        @foreach ($ticketTypes as $t)
            <form id="tk-{{ $t->id }}" method="post" action="{{ route('admin.ticket-types.update', $t) }}">@csrf @method('put')</form>
        @endforeach
        <form id="tk-new" method="post" action="{{ route('admin.events.ticket-types', $event) }}">@csrf</form>
    </div>

    <div class="cols">
        <div class="card flush">
            <div class="card-head"><h2>Orders</h2><small>{{ $orders->count() }} paid</small></div>
            <div class="table-wrap"><table class="list">
                <thead><tr><th>Buyer</th><th class="num">Tickets</th><th class="num">Total</th><th>When</th></tr></thead>
                <tbody>
                @forelse ($orders as $o)
                    <tr class="click">
                        <td><a href="{{ route('admin.orders.show', $o->id) }}">{{ $o->buyer_name }}</a><br><small>{{ $o->email }}</small></td>
                        <td class="num">{{ $o->ticketCount() }}</td>
                        <td class="num">{{ $o->payment_method === 'comp' ? 'Comp' : $M($o->total_cents) }}</td>
                        <td><small>{{ $o->paid_at?->format('M j, g:i A') }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><p class="muted" style="margin:0">No orders yet.</p></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="card">
            <h3>Send comp tickets</h3>
            <form method="post" action="{{ route('admin.events.comp', $event) }}">
                @csrf
                <div class="field"><label>Name</label><input type="text" name="buyer_name" required></div>
                <div class="field"><label>Email</label><input type="email" name="email" required></div>
                <div class="fields-2">
                    <div class="field"><label>Type</label><select name="ticket_type_id">@foreach ($ticketTypes as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div>
                    <div class="field"><label>How many</label><input type="number" name="quantity" value="1" min="1" max="50"></div>
                </div>
                <button class="btn block" @disabled($ticketTypes->isEmpty())>Send free tickets</button>
            </form>
        </div>
    </div>
@endif

@if ($tab === 'door')
    @php $link = route('checkin', $event->ensureCheckinToken()); @endphp
    <div class="cols">
        <div class="card">
            <h2>Door check-in link</h2>
            <p>Give this link to whoever is working the door. It works on any phone without a login, but only for this event.</p>
            <div class="row"><input type="text" readonly value="{{ $link }}" onclick="this.select()" style="flex:1"><a class="btn primary" href="{{ $link }}" target="_blank">Open</a></div>
            <form method="post" action="{{ route('admin.events.checkin-link', $event) }}" style="margin-top:12px" onsubmit="return confirm('Make a new link? The current one will stop working.')">
                @csrf <button class="btn small danger">Reset link</button>
            </form>
        </div>
        <div class="card">
            <h3>At the door so far</h3>
            <dl class="kv">
                <dt>Attendance</dt><dd><strong>{{ $stats['attendance'] }}</strong></dd>
                <dt>Online tickets in</dt><dd>{{ $stats['online_in'] }} of {{ $stats['online_issued'] }}</dd>
                <dt>Door sales</dt><dd>{{ $stats['door_qty'] }}</dd>
                <dt>Free kids</dt><dd>{{ $stats['kids'] }}</dd>
                <dt>Vendors in</dt><dd>{{ $stats['vendors_in'] }} of {{ $stats['vendors_paid'] }}</dd>
            </dl>
        </div>
    </div>
@endif

@if ($tab === 'money')
    <div class="card" style="max-width:640px">
        <h2>Money for this event</h2>
        <table class="list">
            <tr><td>{{ ucfirst($event->space()) }} fees paid online</td><td class="num">{{ $M($money['tables_online']) }}</td></tr>
            <tr><td>Platform fee ({{ config('platform.fees.vendor_percent') }}%)</td><td class="num">−{{ $M($money['tables_fee']) }}</td></tr>
            @if ($money['tables_refunded'])<tr><td>Refunded to vendors</td><td class="num">−{{ $M($money['tables_refunded']) }}</td></tr>@endif
            <tr><td>Tickets sold online</td><td class="num">{{ $M($money['tickets_online']) }}</td></tr>
            <tr><td><strong>Paid out to you</strong> <small>(Stripe, once connected)</small></td><td class="num"><strong>{{ $M($money['payout']) }}</strong></td></tr>
            <tr><td>{{ ucfirst($event->space()) }} fees paid by hand</td><td class="num">{{ $M($money['tables_cash']) }}</td></tr>
            <tr><td>Door: cash</td><td class="num">{{ $M($money['door_cash']) }}</td></tr>
            <tr><td>Door: card</td><td class="num">{{ $M($money['door_card']) }}</td></tr>
            @if ($money['door_other'])<tr><td>Door: other</td><td class="num">{{ $M($money['door_other']) }}</td></tr>@endif
            <tr><td><strong>Collected in person</strong></td><td class="num"><strong>{{ $M($money['collected_in_person']) }}</strong></td></tr>
            <tr><td><strong>Total to you</strong></td><td class="num"><strong>{{ $M($money['total']) }}</strong></td></tr>
        </table>
        <p class="muted" style="font-size:.86rem;margin-top:12px">Ticket buyers also paid {{ $M($money['ticket_fees_paid_by_buyers']) }} in service fees, which go to the platform and don't come out of your share.</p>
    </div>
@endif
@endsection
