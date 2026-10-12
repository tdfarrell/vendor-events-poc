@extends('layouts.site')
@section('width', 'wrap-narrow')
@section('title', 'Book a '.$event->space().' · '.$event->title)

@section('content')
<p><a href="{{ route('site.event', $event) }}">← {{ $event->title }}</a></p>
<h1>Book a {{ $event->space() }}</h1>
<p class="muted">{{ $event->whenWhere() }}</p>

@if (! $open)
    <div class="alert warn">Vendor applications are closed for this event.</div>
@else
    <div class="alert info">
        @switch($event->vendor_payment_mode)
            @case('instant') You'll pay now, and your {{ $event->space() }} is confirmed as soon as payment goes through. @break
            @case('review') You'll pay now to hold your {{ $event->space() }}. The organizer reviews every vendor; if they can't fit you in, you're refunded automatically. @break
            @default Applying is free. If you're approved, you'll get an email with a link to pay and confirm.
        @endswitch
    </div>

    @if ($prefill)
        <p class="muted">Signed in as {{ $prefill->email }}. We filled in your details.</p>
    @else
        <p class="muted">Already have a vendor account? <a href="{{ route('vendor.login') }}">Sign in</a> to fill this in automatically.</p>
    @endif

    <form method="post" class="card" action="{{ route('site.apply', $event) }}">
        @csrf
        <input type="text" name="company_website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

        <fieldset>
            <legend>Your {{ $event->space() }}</legend>
            @foreach ($spaces as $s)
                <label class="check" style="padding:8px 0">
                    <input type="radio" name="table_type_id" value="{{ $s->id }}" required @checked(old('table_type_id', $spaces->firstWhere('available', '>', 0)?->id) == $s->id)>
                    <span><strong>{{ $s->name }}</strong> · {{ \App\Support\Money::format($s->price_cents) }}
                        @if ($s->available > 0)<span class="badge ok">{{ $s->available }} left</span>@else<span class="badge warn">Full: you'll join the waitlist</span>@endif
                        @if ($s->description)<br><small>{{ $s->description }}</small>@endif
                        <br><small>Up to {{ $s->max_per_vendor }} per vendor</small>
                    </span>
                </label>
            @endforeach
            <div class="field" style="max-width:140px">
                <label for="quantity">How many</label>
                <input id="quantity" name="quantity" type="number" min="1" max="20" value="{{ old('quantity', 1) }}" required>
            </div>
        </fieldset>

        <fieldset>
            <legend>About you</legend>
            <div class="field"><label>Business name</label><input type="text" name="business_name" required value="{{ old('business_name', $prefill?->business_name) }}"></div>
            <div class="fields-2">
                <div class="field"><label>Your name</label><input type="text" name="contact_name" required value="{{ old('contact_name', $prefill?->contact_name) }}"></div>
                <div class="field"><label>Phone</label><input type="tel" name="phone" value="{{ old('phone', $prefill?->phone) }}"></div>
            </div>
            <div class="field"><label>Email</label><input type="email" name="email" required value="{{ old('email', $prefill?->email) }}"></div>
            @if ($event->categoryList())
                <div class="field">
                    <label>What you sell</label>
                    <div class="chips">
                        @php $mine = old('categories', array_map('trim', explode(',', (string) $prefill?->categories))); @endphp
                        @foreach ($event->categoryList() as $c)
                            <label class="chip check" style="font-weight:400"><input type="checkbox" name="categories[]" value="{{ $c }}" @checked(in_array($c, $mine, true))> {{ $c }}</label>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="field"><label>Tell the organizer about your inventory</label><textarea name="inventory_description">{{ old('inventory_description') }}</textarea></div>
            <div class="field"><label>Instagram, Facebook or website <span class="hint">optional</span></label><input type="text" name="social_link" value="{{ old('social_link', $prefill?->instagram ?: $prefill?->website) }}"></div>
            <label class="check"><input type="checkbox" name="needs_power" value="1" @checked(old('needs_power'))> I need electricity</label>
            <div class="field" style="margin-top:12px"><label>Anything else? <span class="hint">optional</span></label><textarea name="vendor_notes" style="min-height:64px">{{ old('vendor_notes') }}</textarea></div>
        </fieldset>

        @if ($event->vendor_terms)
            <fieldset>
                <legend>Vendor terms</legend>
                <div style="max-height:160px;overflow:auto;font-size:.9rem;margin-bottom:10px">{!! nl2br(e($event->vendor_terms)) !!}</div>
                <label class="check"><input type="checkbox" name="agree_terms" value="1" required> I agree to the vendor terms</label>
            </fieldset>
        @endif

        <button class="btn primary big block">{{ $event->vendor_payment_mode === 'approve_first' ? 'Send application' : 'Continue to payment' }}</button>
    </form>
@endif
@endsection
