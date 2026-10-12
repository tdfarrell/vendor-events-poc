@extends('layouts.site')
@section('width', 'wrap-narrow')
@section('title', 'Tickets · '.$event->title)

@section('content')
<p><a href="{{ route('site.event', $event) }}">← {{ $event->title }}</a></p>
<h1>Tickets</h1>
<p class="muted">{{ $event->whenWhere() }}</p>

@if (! $selling || $types->isEmpty())
    <div class="alert warn">Online tickets aren't on sale for this event. Tickets may be available at the door.</div>
@else
    <form method="post" class="card" action="{{ route('site.tickets', $event) }}" id="ticket-form">
        @csrf
        @foreach ($types as $t)
            <div class="space-row" style="align-items:center">
                <span><strong>{{ $t->name }}</strong> · {{ $t->price_cents ? \App\Support\Money::format($t->price_cents) : 'Free' }}
                    @if ($t->description)<br><small>{{ $t->description }}</small>@endif
                    @if ($t->available <= 0)<br><span class="badge warn">Sold out</span>@elseif ($t->available < 20)<br><small>{{ $t->available }} left</small>@endif
                </span>
                <select name="qty[{{ $t->id }}]" data-price="{{ $t->price_cents }}" style="width:84px" @disabled($t->available <= 0)>
                    @for ($i = 0; $i <= min($t->max_per_order, $t->available); $i++)
                        <option value="{{ $i }}" @selected(old("qty.{$t->id}") == $i)>{{ $i }}</option>
                    @endfor
                </select>
            </div>
        @endforeach
        <hr>
        <div class="fields-2">
            <div class="field"><label>Name</label><input type="text" name="buyer_name" required value="{{ old('buyer_name') }}"></div>
            <div class="field"><label>Email <span class="hint">tickets go here</span></label><input type="email" name="email" required value="{{ old('email') }}"></div>
        </div>
        <p class="muted" id="ticket-total" style="font-size:.92rem">A {{ \App\Support\Money::format(config('platform.fees.ticket_cents')) }} service fee is added per paid ticket.</p>
        <button class="btn primary big block">Continue</button>
    </form>
    <script>
        (function () {
            const fee = {{ (int) config('platform.fees.ticket_cents') }};
            const fmt = c => '$' + (c / 100).toFixed(2);
            const form = document.getElementById('ticket-form');
            const out = document.getElementById('ticket-total');
            function update() {
                let sub = 0, paid = 0;
                form.querySelectorAll('select[data-price]').forEach(s => {
                    const q = +s.value, p = +s.dataset.price;
                    sub += q * p; if (p > 0) paid += q;
                });
                if (sub === 0 && paid === 0) return;
                out.textContent = `Tickets ${fmt(sub)} + service fee ${fmt(paid * fee)} = ${fmt(sub + paid * fee)}`;
            }
            form.addEventListener('change', update);
        })();
    </script>
@endif
@endsection
