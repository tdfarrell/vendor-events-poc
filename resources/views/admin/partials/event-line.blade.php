<a class="ev-admin-card" href="{{ route('admin.events.show', $e) }}">
    <div class="ev-art"><x-poster :event="$e" /></div>
    <div>
        <h3>{{ $e->title }}</h3>
        <div class="muted" style="font-size:.92rem">{{ $e->starts_on?->format('D, M j, Y') ?? 'Date TBA' }}@if ($e->town) · {{ $e->town }}@endif</div>
        <div class="row" style="gap:6px;margin-top:6px">
            <span class="badge {{ $e->status }}">{{ \App\Models\Event::STATUSES[$e->status] }}</span>
            <span class="badge">{{ $e->typeConfig()['emoji'] }} {{ $e->typeLabel() }}</span>
            @if ($e->notice)<span class="badge bad">{{ ucfirst($e->notice) }}</span>@endif
        </div>
    </div>
    <div class="nums">
        <div><b>{{ $e->confirmed_count }}</b><span>vendors</span></div>
        <div><b>{{ $e->review_count ?: '–' }}</b><span>to review</span></div>
    </div>
</a>
