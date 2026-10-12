@props(['date'])
<div class="datebox" aria-hidden="true">
    @if ($date)
        <span class="m">{{ $date->format('M') }}</span>
        <span class="d">{{ $date->format('j') }}</span>
        <span class="w">{{ $date->format('D') }}</span>
    @else
        <span class="m">TBA</span>
    @endif
</div>
