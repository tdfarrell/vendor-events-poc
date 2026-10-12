@extends('layouts.admin')
@section('page', $event->exists ? 'Edit '.$event->title : 'New event')

@section('content')
@if ($chooseType)
    <h1>What kind of event?</h1>
    <p class="muted">We'll set up the usual spaces, prices and vendor categories. You can change everything afterward.</p>
    <div class="grid grid-3" style="margin-top:16px">
        @foreach (config('event_types') as $key => $t)
            <a class="card" style="text-decoration:none;color:inherit" href="{{ route('admin.events.create', ['type' => $key]) }}">
                <h3>{{ $t['label'] }}</h3>
                <small>{{ collect($t['space_types'])->pluck('name')->join(', ') }}</small>
            </a>
        @endforeach
    </div>
@else
    <p><a href="{{ $event->exists ? route('admin.events.show', $event) : route('admin.dashboard') }}">← Back</a></p>
    <h1>{{ $event->exists ? 'Edit event' : 'New '.Str::lower($event->typeLabel()) }}</h1>
    <form method="post" action="{{ $event->exists ? route('admin.events.update', $event) : route('admin.events.store') }}">
        @csrf @if ($event->exists) @method('put') @endif
        <div class="cols">
            <div>
                <fieldset>
                    <legend>Basics</legend>
                    <div class="field"><label>Title</label><input type="text" name="title" required value="{{ old('title', $event->title) }}"></div>
                    <div class="fields-2">
                        <div class="field"><label>Type</label>
                            <select name="type">@foreach (config('event_types') as $k => $t)<option value="{{ $k }}" @selected(old('type', $event->type) === $k)>{{ $t['label'] }}</option>@endforeach</select>
                        </div>
                        <div class="field"><label>Date</label><input type="date" name="starts_on" value="{{ old('starts_on', $event->starts_on?->format('Y-m-d')) }}"></div>
                    </div>
                    <div class="fields-2">
                        <div class="field"><label>Hours</label><input type="text" name="hours" placeholder="10 AM – 3 PM" value="{{ old('hours', $event->hours) }}"></div>
                        <div class="field"><label>Vendor setup</label><input type="text" name="vendor_setup" placeholder="8 – 9:30 AM" value="{{ old('vendor_setup', $event->vendor_setup) }}"></div>
                    </div>
                    <div class="field"><label>Venue</label><input type="text" name="venue_name" value="{{ old('venue_name', $event->venue_name) }}"></div>
                    <div class="fields-2">
                        <div class="field"><label>Street address</label><input type="text" name="venue_address" value="{{ old('venue_address', $event->venue_address) }}"></div>
                        <div class="field"><label>Town <span class="hint">used by the directory</span></label><input type="text" name="town" value="{{ old('town', $event->town) }}"></div>
                    </div>
                    <div class="field"><label>Description</label><textarea name="description">{{ old('description', $event->description) }}</textarea></div>
                    <div class="field"><label>Admission note</label><input type="text" name="admission_note" placeholder="$5 at the door, kids free" value="{{ old('admission_note', $event->admission_note) }}"></div>
                </fieldset>
                <fieldset>
                    <legend>Vendors</legend>
                    <label class="check" style="margin-bottom:12px"><input type="checkbox" name="applications_open" value="1" @checked(old('applications_open', $event->applications_open))> Accept vendor applications online</label>
                    <div class="field"><label>How vendors pay</label>
                        <select name="vendor_payment_mode">@foreach (\App\Models\Event::PAYMENT_MODES as $k => $l)<option value="{{ $k }}" @selected(old('vendor_payment_mode', $event->vendor_payment_mode) === $k)>{{ $l }}</option>@endforeach</select>
                    </div>
                    <div class="field" style="max-width:220px"><label>Days to pay after approval</label><input type="number" name="payment_window_days" min="1" max="60" value="{{ old('payment_window_days', $event->payment_window_days) }}"></div>
                    <div class="field"><label>Vendor categories <span class="hint">comma separated; vendors pick from these</span></label><input type="text" name="categories" value="{{ old('categories', $event->categories) }}"></div>
                    <div class="field"><label>Vendor terms <span class="hint">optional; vendors must agree</span></label><textarea name="vendor_terms">{{ old('vendor_terms', $event->vendor_terms) }}</textarea></div>
                </fieldset>
            </div>
            <div>
                <fieldset>
                    <legend>Visibility</legend>
                    <div class="field"><label>Status</label>
                        <select name="status">@foreach (\App\Models\Event::STATUSES as $k => $l)<option value="{{ $k }}" @selected(old('status', $event->status ?? 'draft') === $k)>{{ $l }}</option>@endforeach</select>
                        <div class="hint">Published events appear on your site and in the {{ config('platform.name') }} directory.</div>
                    </div>
                    <div class="field"><label>Notice</label>
                        <select name="notice"><option value="">None</option><option value="cancelled" @selected(old('notice', $event->notice) === 'cancelled')>Cancelled</option><option value="rescheduled" @selected(old('notice', $event->notice) === 'rescheduled')>Rescheduled</option></select>
                    </div>
                    <div class="field"><label>Notice text</label><input type="text" name="notice_text" value="{{ old('notice_text', $event->notice_text) }}"></div>
                </fieldset>
                <fieldset>
                    <legend>Tickets</legend>
                    <label class="check" style="margin-bottom:12px"><input type="checkbox" name="tickets_on_sale" value="1" @checked(old('tickets_on_sale', $event->tickets_on_sale))> Sell tickets online</label>
                    <div class="field"><label>Total capacity <span class="hint">optional</span></label><input type="number" name="ticket_capacity" min="1" value="{{ old('ticket_capacity', $event->ticket_capacity) }}"></div>
                </fieldset>
                <button class="btn primary big block">{{ $event->exists ? 'Save event' : 'Create event' }}</button>
            </div>
        </div>
    </form>
@endif
@endsection
