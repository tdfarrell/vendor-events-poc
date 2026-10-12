@props(['name' => '?', 'src' => '', 'size' => 'md'])
@php
    $words = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: [], fn ($w) => preg_match('/^[\p{L}\p{N}]/u', $w))) ?: ['?'];
    $ini = mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1).mb_substr($words[1] ?? '', 0, 1));
    $hue = crc32($name) % 360;
@endphp
@if ($src)
    <img class="av av-{{ $size }}" src="{{ $src }}" alt="" loading="lazy">
@else
    <span class="av av-{{ $size }}" style="--h: {{ $hue }}" aria-hidden="true">{{ $ini }}</span>
@endif
