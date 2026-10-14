{{-- Fields for one room; $room may be a new VenueRoom. $p prefixes ids so several forms can share a page. --}}
@php $p = $p ?? 'new'; $picked = $room->amenities ?? []; @endphp
<div class="field"><label for="{{ $p }}-name">Room name</label><input id="{{ $p }}-name" type="text" name="name" required value="{{ $room->name }}" placeholder="Main hall"></div>
<div class="fields-2">
    <div class="field"><label for="{{ $p }}-seated">Seated guests</label><input id="{{ $p }}-seated" type="number" min="0" name="seated" value="{{ $room->seated }}"></div>
    <div class="field"><label for="{{ $p }}-standing">Standing guests</label><input id="{{ $p }}-standing" type="number" min="0" name="standing" value="{{ $room->standing }}"></div>
</div>
<div class="fields-2">
    <div class="field"><label for="{{ $p }}-from">Price from ($)</label><input id="{{ $p }}-from" type="number" min="0" name="price_from" value="{{ $room->price_from }}"></div>
    <div class="field"><label for="{{ $p }}-to">Up to ($) <span class="hint">optional</span></label><input id="{{ $p }}-to" type="number" min="0" name="price_to" value="{{ $room->price_to }}"></div>
    <div class="field"><label for="{{ $p }}-unit">Charged</label><select id="{{ $p }}-unit" name="price_unit">@foreach (config('venues.price_units') as $k => $l)<option value="{{ $k }}" @selected(($room->price_unit ?: 'event') === $k)>{{ $l }}</option>@endforeach</select></div>
</div>
<div class="field"><label for="{{ $p }}-desc">Description <span class="hint">optional</span></label><textarea id="{{ $p }}-desc" name="description" style="min-height:64px" placeholder="Hardwood floor, round tables for 10, kitchen pass-through…">{{ $room->description }}</textarea></div>
<fieldset class="amenity-picks" style="border:0;padding:0;margin:0 0 14px">
    <legend class="sr-only">Amenities</legend>
    @foreach (config('venues.amenities') as $k => [$ic, $label])
        <label><input type="checkbox" name="amenities[]" value="{{ $k }}" @checked(in_array($k, $picked ?? [], true))> {{ $ic }} {{ $label }}</label>
    @endforeach
</fieldset>
