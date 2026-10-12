{{-- A poster card for one event. $event (with organizer loaded); optional $faces (applications with vendor). --}}
@php $faces = $faces ?? collect(); @endphp
<a class="ev-card" href="{{ $event->publicUrl() }}" style="--brand: {{ $event->organizer->brand_color }}">
    <div class="ev-art">
        <x-poster :event="$event" />
        @if ($event->starts_on)
            <span class="date-chip"><span class="m">{{ $event->starts_on->format('M') }}</span><br><span class="d">{{ $event->starts_on->format('j') }}</span></span>
        @endif
        <span class="art-tag">{{ $event->typeConfig()['emoji'] }} {{ $event->typeLabel() }}</span>
        <span class="ev-flags">
            @if ($event->notice === 'cancelled')<span class="flag off">Cancelled</span>@elseif ($event->notice === 'rescheduled')<span class="flag">Rescheduled</span>@endif
            @if ($event->applications_open && $event->notice !== 'cancelled')<span class="flag hot">Booking vendors</span>@endif
        </span>
    </div>
    <div class="ev-body">
        <span class="ev-when">{{ $event->starts_on?->format('D, M j') ?? 'Date to be announced' }}@if ($event->hours) · {{ $event->hours }}@endif</span>
        <h3>{{ $event->title }}</h3>
        <span class="ev-where">{{ collect([$event->venue_name, $event->town])->filter()->join(', ') }}</span>
        <span class="ev-by">
            <span>by {{ $event->organizer->name }}</span>
            @if ($faces->isNotEmpty())
                <span class="av-stack" title="{{ $event->vendor_count ?? $faces->count() }} vendors coming">
                    @foreach ($faces as $a)<x-avatar :name="$a->business_name" :src="$a->vendor?->logoUrl() ?? ''" size="sm" />@endforeach
                    @if (($event->vendor_count ?? 0) > $faces->count())<span class="more">+{{ $event->vendor_count - $faces->count() }}</span>@endif
                </span>
            @endif
        </span>
    </div>
</a>
