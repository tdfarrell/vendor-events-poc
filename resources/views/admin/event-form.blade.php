@extends('layouts.admin')
@section('page', $event->exists ? 'Edit '.$event->title : 'New event')
@section('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endsection

@section('content')
@if ($chooseType)
    <a class="crumb" href="{{ route('admin.dashboard') }}">← Events</a>
    <div class="admin-head"><div><h1>What kind of event?</h1>
        <p class="muted" style="margin:0">We'll set up the usual spaces, prices and vendor categories. You can change all of it afterward.</p></div></div>
    <div class="cat-rail" style="margin-top:0">
        @foreach (config('event_types') as $key => $t)
            <a class="cat" href="{{ route('admin.events.create', ['type' => $key]) }}" style="background: linear-gradient(140deg, {{ $t['colors'][0] }}, {{ $t['colors'][1] }})">
                <span class="e" aria-hidden="true">{{ $t['emoji'] }}</span>
                <span><b>{{ $t['label'] }}</b><br><small>{{ collect($t['space_types'])->pluck('name')->join(', ') }}</small></span>
            </a>
        @endforeach
    </div>
@else
    @php $amen = old('amenities', $event->amenities ?? []); $faq = old('faq', $event->faq ?? []); @endphp
    <a class="crumb" href="{{ $event->exists ? route('admin.events.show', $event) : route('admin.dashboard') }}">← {{ $event->exists ? $event->title : 'Events' }}</a>
    <div class="admin-head"><h1>{{ $event->exists ? 'Edit event' : 'New '.Str::lower($event->typeLabel()) }}</h1></div>

    <div class="editor">
        <nav class="editor-nav" aria-label="Sections">
            <a href="#basics">Basics</a>
            <a href="#look">Cover photo</a>
            @if ($event->exists)<a href="#photos">Photo gallery</a>@endif
            <a href="#location">Location</a>
            <a href="#details">Details for attendees</a>
            <a href="#vendors">Vendors</a>
            <a href="#tickets">Tickets</a>
            <a href="#visibility">Visibility</a>
        </nav>

        <div>
            <form method="post" enctype="multipart/form-data" action="{{ $event->exists ? route('admin.events.update', $event) : route('admin.events.store') }}" id="eventForm">
                @csrf @if ($event->exists) @method('put') @endif

                <fieldset id="basics">
                    <legend>Basics</legend>
                    <div class="field"><label for="title">Event name</label><input id="title" type="text" name="title" required value="{{ old('title', $event->title) }}" placeholder="Holiday Card Show"></div>
                    <div class="fields-2">
                        <div class="field"><label>Type</label>
                            <select name="type">@foreach (config('event_types') as $k => $t)<option value="{{ $k }}" @selected(old('type', $event->type) === $k)>{{ $t['emoji'] }} {{ $t['label'] }}</option>@endforeach</select>
                        </div>
                        <div class="field"><label>Date</label><input type="date" name="starts_on" value="{{ old('starts_on', $event->starts_on?->format('Y-m-d')) }}"></div>
                    </div>
                    <div class="fields-2">
                        <div class="field"><label>Hours</label><input type="text" name="hours" placeholder="10 AM – 3 PM" value="{{ old('hours', $event->hours) }}"></div>
                        <div class="field"><label>Vendor setup</label><input type="text" name="vendor_setup" placeholder="8 – 9:30 AM" value="{{ old('vendor_setup', $event->vendor_setup) }}"></div>
                    </div>
                </fieldset>

                <fieldset id="look">
                    <legend>Cover photo</legend>
                    <div class="cover-edit">
                        <div class="ev-art"><x-poster :event="$event" /></div>
                        <div>
                            <p class="muted" style="margin-top:0;font-size:.92rem">A wide photo from a past event works best (at least 1600 pixels across). Without one, your event gets a poster in its category's colors, like the one shown.</p>
                            <div class="field"><input type="file" name="cover" accept="image/*"></div>
                            @if ($event->cover_path)<label class="check"><input type="checkbox" name="remove_cover" value="1"> Remove the cover photo and use the poster</label>@endif
                        </div>
                    </div>
                </fieldset>

                <fieldset id="location">
                    <legend>Location</legend>
                    <div class="field"><label>Venue</label><input type="text" name="venue_name" value="{{ old('venue_name', $event->venue_name) }}" placeholder="Watertown Elks Lodge"></div>
                    <div class="fields-2">
                        <div class="field"><label>Street address</label><input type="text" name="venue_address" value="{{ old('venue_address', $event->venue_address) }}"></div>
                        <div class="field"><label>Town</label><input type="text" name="town" value="{{ old('town', $event->town) }}"></div>
                    </div>
                    <div class="field"><label>Parking <span class="hint">optional</span></label><input type="text" name="parking_info" value="{{ old('parking_info', $event->parking_info) }}" placeholder="Free lot behind the building; overflow on Main St"></div>
                    <label>Map pin</label>
                    <div class="pin-map" id="pinMap"></div>
                    <p class="hint" style="margin:6px 0 12px">We place the pin from the address when you save. Drag it if it's off; your spot wins.</p>
                    <input type="hidden" name="lat" id="lat" value="{{ old('lat', $event->lat) }}">
                    <input type="hidden" name="lng" id="lng" value="{{ old('lng', $event->lng) }}">
                    <input type="hidden" name="pin_moved" id="pinMoved" value="0">
                </fieldset>

                <fieldset id="details">
                    <legend>Details for attendees</legend>
                    <div class="field"><label>Description</label><textarea name="description" rows="6" placeholder="What's here, who's coming, why it's worth the drive.">{{ old('description', $event->description) }}</textarea></div>
                    <div class="field"><label>Admission</label><input type="text" name="admission_note" placeholder="$5 at the door, kids free" value="{{ old('admission_note', $event->admission_note) }}"></div>
                    <label>Good to know</label>
                    <div class="tick-grid">
                        @foreach (config('platform.amenities') as $k => [$icon, $label])
                            <label class="tick"><input type="checkbox" name="amenities[]" value="{{ $k }}" @checked(in_array($k, $amen, true))> {{ $icon }} {{ $label }}</label>
                        @endforeach
                    </div>
                    <label>Questions people ask <span class="hint">shown on the event page</span></label>
                    <div id="faqRows">
                        @foreach (array_merge($faq, [['q' => '', 'a' => '']]) as $i => $f)
                            <div class="faq-row">
                                <input type="text" name="faq[{{ $i }}][q]" value="{{ $f['q'] ?? '' }}" placeholder="Is there an ATM?">
                                <textarea name="faq[{{ $i }}][a]" rows="1" style="min-height:46px" placeholder="Yes, by the front door.">{{ $f['a'] ?? '' }}</textarea>
                                <button type="button" class="btn small" data-remove-faq aria-label="Remove question">Remove</button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn small" id="addFaq" style="margin-bottom:14px">Add a question</button>
                </fieldset>

                <fieldset id="vendors">
                    <legend>Vendors</legend>
                    <label class="check" style="margin-bottom:14px"><input type="checkbox" name="applications_open" value="1" @checked(old('applications_open', $event->applications_open))> Take vendor bookings online</label>
                    <div class="field"><label>How vendors pay</label>
                        <select name="vendor_payment_mode">@foreach (\App\Models\Event::PAYMENT_MODES as $k => $l)<option value="{{ $k }}" @selected(old('vendor_payment_mode', $event->vendor_payment_mode) === $k)>{{ $l }}</option>@endforeach</select>
                    </div>
                    <div class="fields-2">
                        <div class="field"><label>Days to pay after approval</label><input type="number" name="payment_window_days" min="1" max="60" value="{{ old('payment_window_days', $event->payment_window_days) }}"></div>
                    </div>
                    <div class="field"><label>Vendor categories <span class="hint">comma separated; vendors pick from these</span></label><input type="text" name="categories" value="{{ old('categories', $event->categories) }}"></div>
                    <div class="field"><label>Vendor terms <span class="hint">optional; vendors must agree</span></label><textarea name="vendor_terms">{{ old('vendor_terms', $event->vendor_terms) }}</textarea></div>
                    @unless ($event->exists)<p class="hint">Spaces and prices are set up from the {{ Str::lower($event->typeLabel()) }} template after you create the event.</p>@endunless
                </fieldset>

                <fieldset id="tickets">
                    <legend>Tickets</legend>
                    <label class="check" style="margin-bottom:14px"><input type="checkbox" name="tickets_on_sale" value="1" @checked(old('tickets_on_sale', $event->tickets_on_sale))> Sell tickets online</label>
                    <div class="fields-2"><div class="field"><label>Total capacity <span class="hint">optional</span></label><input type="number" name="ticket_capacity" min="1" value="{{ old('ticket_capacity', $event->ticket_capacity) }}"></div></div>
                </fieldset>

                <fieldset id="visibility">
                    <legend>Visibility</legend>
                    <div class="fields-2">
                        <div class="field"><label>Status</label>
                            <select name="status">@foreach (\App\Models\Event::STATUSES as $k => $l)<option value="{{ $k }}" @selected(old('status', $event->status ?? 'draft') === $k)>{{ $l }}</option>@endforeach</select>
                            <div class="hint">Published events show on your site and in the {{ config('platform.name') }} directory.</div>
                        </div>
                        <div class="field"><label>Notice</label>
                            <select name="notice"><option value="">None</option><option value="cancelled" @selected(old('notice', $event->notice) === 'cancelled')>Cancelled</option><option value="rescheduled" @selected(old('notice', $event->notice) === 'rescheduled')>Rescheduled</option></select>
                        </div>
                    </div>
                    <div class="field"><label>Notice text</label><input type="text" name="notice_text" value="{{ old('notice_text', $event->notice_text) }}" placeholder="Moved to Sunday because of snow"></div>
                </fieldset>

                <div class="editor-save">
                    <a class="btn" href="{{ $event->exists ? route('admin.events.show', $event) : route('admin.dashboard') }}">Cancel</a>
                    <button class="btn fire">{{ $event->exists ? 'Save event' : 'Create event' }}</button>
                </div>
            </form>

            @if ($event->exists)
                <fieldset id="photos" style="margin-top:24px">
                    <legend>Photo gallery</legend>
                    <p class="muted" style="margin-top:0;font-size:.92rem">Photos from past events show at the bottom of the event page.</p>
                    @if ($event->photos->isNotEmpty())
                        <div class="thumbs">
                            @foreach ($event->photos as $p)
                                <div class="t"><img src="{{ $p->url() }}" alt="">
                                    <form method="post" action="{{ route('admin.events.photos.delete', [$event, $p]) }}">@csrf<button aria-label="Remove photo">×</button></form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <form method="post" enctype="multipart/form-data" action="{{ route('admin.events.photos', $event) }}" class="row" style="margin-bottom:14px">
                        @csrf
                        <input type="file" name="photos[]" accept="image/*" multiple required style="flex:1">
                        <button class="btn small">Upload photos</button>
                    </form>
                </fieldset>
            @endif
        </div>
    </div>
@endif
@endsection

@unless ($chooseType)
@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
(function () {
    // Map pin
    var lat = document.getElementById('lat'), lng = document.getElementById('lng'), moved = document.getElementById('pinMoved');
    var has = lat.value !== '' && lng.value !== '';
    var center = has ? [+lat.value, +lng.value] : @json(config('platform.map_center'));
    var map = L.map('pinMap', { scrollWheelZoom: false }).setView(center, has ? 15 : 8);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
    var marker = L.marker(center, { draggable: true, opacity: has ? 1 : .55 }).addTo(map);
    function setPin(ll) { lat.value = ll.lat.toFixed(6); lng.value = ll.lng.toFixed(6); moved.value = '1'; marker.setOpacity(1); }
    marker.on('dragend', function () { setPin(marker.getLatLng()); });
    map.on('click', function (e) { marker.setLatLng(e.latlng); setPin(e.latlng); });

    // FAQ rows
    var rows = document.getElementById('faqRows'), n = rows.children.length;
    document.getElementById('addFaq').addEventListener('click', function () {
        var r = rows.lastElementChild.cloneNode(true);
        r.querySelectorAll('input, textarea').forEach(function (el) { el.value = ''; el.name = el.name.replace(/faq\[\d+\]/, 'faq[' + n + ']'); });
        rows.appendChild(r); n++; r.querySelector('input').focus();
    });
    rows.addEventListener('click', function (e) {
        if (!e.target.matches('[data-remove-faq]')) return;
        var row = e.target.closest('.faq-row');
        if (rows.children.length > 1) row.remove(); else row.querySelectorAll('input, textarea').forEach(function (el) { el.value = ''; });
    });
})();
</script>
@endpush
@endunless
