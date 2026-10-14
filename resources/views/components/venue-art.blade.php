@props(['venue', 'class' => ''])
@php $k = $venue->kindConfig(); @endphp
@if ($venue->cover_path)
    <img class="venue-art {{ $class }}" src="{{ $venue->coverUrl() }}" alt="" loading="lazy">
@else
    <div class="venue-art gen {{ $class }}" style="background: linear-gradient(140deg, {{ $k['colors'][0] }}, {{ $k['colors'][1] }})" aria-hidden="true">
        <span class="e">{{ $k['emoji'] }}</span>
        <span class="n">{{ Str::limit($venue->name, 40) }}</span>
    </div>
@endif
