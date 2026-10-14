@extends('layouts.admin')
@section('page', $venue->exists ? $venue->name : 'List your venue')

@section('content')
<a class="crumb" href="{{ route('admin.venues.index') }}">← Venue</a>
<div class="admin-head">
    <div>
        <h1>{{ $venue->exists ? $venue->name : 'List your venue' }}</h1>
        <p class="muted" style="margin:0">{{ $venue->exists ? 'Shown in the directory at '.$venue->url() : 'Tell renters what you have. You can add rooms once this is saved.' }}</p>
    </div>
    @if ($venue->exists)<a class="btn" href="{{ $venue->url() }}" target="_blank">View page ↗</a>@endif
</div>

<div class="cols">
    <form method="post" enctype="multipart/form-data" action="{{ $venue->exists ? route('admin.venues.update', $venue->id) : route('admin.venues.store') }}" class="card lift">
        @csrf @if ($venue->exists) @method('put') @endif
        <h2>About the venue</h2>
        <div class="field"><label for="v-name">Venue name</label><input id="v-name" type="text" name="name" required value="{{ old('name', $venue->name) }}"></div>
        <div class="field"><label for="v-kind">Kind of venue</label><select id="v-kind" name="kind">@foreach (config('venues.kinds') as $k => $t)<option value="{{ $k }}" @selected(old('kind', $venue->kind) === $k)>{{ $t['emoji'] }} {{ $t['label'] }}</option>@endforeach</select></div>
        <div class="fields-2">
            <div class="field"><label for="v-address">Street address</label><input id="v-address" type="text" name="address" value="{{ old('address', $venue->address) }}"></div>
            <div class="field"><label for="v-town">Town</label><input id="v-town" type="text" name="town" required value="{{ old('town', $venue->town) }}"></div>
        </div>
        <div class="field"><label for="v-about">Describe it <span class="hint">what it's good for, what's included</span></label><textarea id="v-about" name="about" rows="4">{{ old('about', $venue->about) }}</textarea></div>
        <div class="field"><label for="v-rules">House rules <span class="hint">alcohol, outside catering, insurance, deposit, end time</span></label><textarea id="v-rules" name="rules" rows="4">{{ old('rules', $venue->rules) }}</textarea></div>
        <div class="field"><label for="v-parking">Parking</label><input id="v-parking" type="text" name="parking_info" value="{{ old('parking_info', $venue->parking_info) }}" placeholder="Free lot for 60 cars"></div>
        <div class="fields-2">
            <div class="field"><label for="v-email">Requests go to</label><input id="v-email" type="email" name="contact_email" required value="{{ old('contact_email', $venue->contact_email) }}"></div>
            <div class="field"><label for="v-phone">Phone <span class="hint">shown on your page</span></label><input id="v-phone" type="tel" name="phone" value="{{ old('phone', $venue->phone) }}"></div>
        </div>
        <div class="field"><label for="v-web">Website <span class="hint">optional</span></label><input id="v-web" type="url" name="website" value="{{ old('website', $venue->website) }}" placeholder="https://"></div>
        <div class="field">
            <label for="v-cover">Cover photo <span class="hint">a wide photo of your main room works best</span></label>
            @if ($venue->cover_path)<div style="aspect-ratio:21/9;border-radius:12px;overflow:hidden;margin-bottom:8px"><x-venue-art :venue="$venue" /></div>
                <label class="check" style="margin-bottom:8px"><input type="checkbox" name="remove_cover" value="1"> Remove this photo</label>@endif
            <input id="v-cover" type="file" name="cover" accept="image/*">
        </div>
        <label class="check" style="margin-bottom:16px"><input type="checkbox" name="hidden" value="1" @checked(old('hidden', $venue->status === 'hidden'))> Hide from the directory for now</label>
        <button class="btn fire">{{ $venue->exists ? 'Save venue' : 'Save and add rooms' }}</button>
    </form>

    <aside class="stack" id="rooms">
        @if ($venue->exists)
            <div class="card">
                <h2>Rooms</h2>
                <p class="muted" style="font-size:.92rem">Add each space you rent: the hall, a meeting room, the pavilion out back.</p>
                @foreach ($venue->rooms as $room)
                    <details class="room-edit" id="room-{{ $room->id }}" @if ($loop->first && $venue->rooms->count() === 1) open @endif>
                        <summary class="row between" style="cursor:pointer"><strong>{{ $room->name }}</strong><small class="muted">{{ $room->capacityText() }} · {{ $room->priceText() }}</small></summary>
                        <form method="post" action="{{ route('admin.rooms.update', $room->id) }}" style="margin-top:12px">
                            @csrf @method('put')
                            @include('admin.partials.room-fields', ['room' => $room, 'p' => 'r'.$room->id])
                            <div class="row between">
                                <button class="btn small fire">Save room</button>
                                <button class="btn small" form="del-{{ $room->id }}">Remove</button>
                            </div>
                        </form>
                        <form id="del-{{ $room->id }}" method="post" action="{{ route('admin.rooms.delete', $room->id) }}">@csrf</form>
                    </details>
                @endforeach
                <details class="room-edit" @if ($venue->rooms->isEmpty()) open @endif>
                    <summary style="cursor:pointer"><strong>+ Add a room</strong></summary>
                    <form method="post" action="{{ route('admin.venues.rooms', $venue->id) }}" style="margin-top:12px">
                        @csrf
                        @include('admin.partials.room-fields', ['room' => new \App\Models\VenueRoom(['price_unit' => 'event'])])
                        <button class="btn small fire">Add room</button>
                    </form>
                </details>
            </div>
        @else
            <div class="card">
                <h3>How rental requests work</h3>
                <ol style="padding-left:18px;margin:0;font-size:.94rem">
                    <li>People find your venue in the directory or from a link you share.</li>
                    <li>They pick a date, guest count and room, and send a request.</li>
                    <li>You get an email and see it here. Reply from either place.</li>
                </ol>
            </div>
        @endif
    </aside>
</div>
@endsection
