{{-- A compact row in the browse list, linked to its pin on the map. $item = ['kind', 'model'] --}}
@php $m = $item['model']; $isEvent = $item['kind'] === 'event'; @endphp
<a @class(['ev-row', 'listing' => ! $isEvent]) href="{{ $isEvent ? $m->publicUrl() : route('listings.show', $m) }}" data-pin="{{ $item['kind'] }}-{{ $m->id }}"
   @if ($isEvent) style="--brand: {{ $m->organizer->brand_color }}" @endif>
    <div class="ev-art">
        @if ($isEvent)
            <x-poster :event="$m" />
        @else
            <span aria-hidden="true">{{ $m->typeConfig()['emoji'] }}</span>
        @endif
    </div>
    <div>
        <span class="ev-when">{{ $m->starts_on?->format('D, M j') ?? 'Date to be announced' }}@if ($m->hours) · {{ $m->hours }}@endif</span>
        <h3>{{ $m->title }}</h3>
        <div class="ev-where">{{ collect([$m->venue_name, $m->town])->filter()->join(', ') }}</div>
        <div class="row" style="gap:6px;margin-top:6px">
            <span class="badge">{{ $m->typeConfig()['emoji'] }} {{ $m->typeLabel() }}</span>
            @if ($isEvent)
                @if ($m->notice === 'cancelled')<span class="badge cancelled">Cancelled</span>@endif
                @if ($m->tickets_on_sale)<span class="badge brand">Tickets online</span>@endif
                @if ($m->applications_open)<span class="badge ok">Booking vendors</span>@endif
                @if (($m->vendor_count ?? 0) > 0)<span class="badge">{{ $m->vendor_count }} vendors</span>@endif
            @else
                <span class="badge">Listed{{ $m->host_name ? ' · '.$m->host_name : '' }}</span>
            @endif
        </div>
    </div>
</a>
