@props(['size' => 32, 'word' => true, 'light' => false])
{{-- Bonfire mark: flame tongues over crossed logs. Original artwork. --}}
<span class="logo {{ $light ? 'logo-light' : '' }}">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="bf-flame" x1=".2" y1="1" x2=".6" y2="0">
                <stop offset="0" stop-color="#E8336D"/><stop offset=".5" stop-color="#FF6A2B"/><stop offset="1" stop-color="#FFB938"/>
            </linearGradient>
        </defs>
        <rect x="7" y="37" width="34" height="6" rx="3" transform="rotate(-14 24 40)" fill="currentColor" opacity=".9"/>
        <rect x="7" y="37" width="34" height="6" rx="3" transform="rotate(14 24 40)" fill="currentColor"/>
        <path d="M24 3c5.5 7.2 13 12.6 12.2 23.6C35.6 34.8 30.6 39 24 39s-12-4.4-12.3-11.6c-.3-6.9 4-10.6 7.1-15.6.8 3.9 2.3 6.4 4.4 8 .9-5.6.6-11 .8-16.8z" fill="url(#bf-flame)"/>
        <path d="M24.2 20.5c3.4 4 6.4 7 6 11.6-.3 3.6-2.9 6.4-6.2 6.4s-6-2.6-6-6.2c0-3.2 2.1-4.9 3.6-7.3.6 1.8 1.4 3 2.5 3.8.5-2.6.3-5.3.1-8.3z" fill="#FFE29A"/>
    </svg>
    @if ($word)<span class="logo-word">{{ config('platform.name') }}</span>@endif
</span>
