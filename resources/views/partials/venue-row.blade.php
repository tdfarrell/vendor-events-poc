{{-- A venue in the browse list, linked to its pin on the map. --}}
@php $max = $venue->maxGuests(); $from = $venue->priceFrom(); @endphp
<a class="ev-row venue-row" href="{{ route('venues.show', $venue->slug) }}" data-pin="venue-{{ $venue->id }}">
    <div class="ev-art"><x-venue-art :venue="$venue" /></div>
    <div>
        <span class="ev-when">{{ $venue->kindConfig()['emoji'] }} {{ $venue->kindLabel() }} · {{ $venue->town }}</span>
        <h3>{{ $venue->name }}</h3>
        <div class="ev-where">
            {{ $venue->rooms->count() }} {{ Str::plural('room', $venue->rooms->count()) }}@if ($max) · up to {{ number_format($max) }} guests @endif
            @if ($from) · from ${{ number_format($from) }}@endif
        </div>
        <div class="row" style="gap:6px;margin-top:6px">
            @foreach (array_slice($venue->amenityKeys(), 0, 4) as $a)
                @php [$ic, $label] = config('venues.amenities.'.$a); @endphp
                <span class="badge">{{ $ic }} {{ $label }}</span>
            @endforeach
            @unless ($venue->isClaimed())<span class="badge">Listed</span>@endunless
        </div>
    </div>
</a>
