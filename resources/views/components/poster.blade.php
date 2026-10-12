@props(['event', 'title' => false, 'class' => ''])
@if ($event->cover_path)
    <img class="poster {{ $class }}" src="{{ $event->coverUrl() }}" alt="" loading="lazy">
@else
    {!! \App\Support\Poster::svg($event->type ?: 'card_show', (int) ($event->id ?? 0), $title ? $event->title : null, $title ? $event->starts_on?->format('D, M j') : null, $class) !!}
@endif
