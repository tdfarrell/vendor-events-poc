@if (session('status'))
    <div class="alert ok" role="status">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="alert bad" role="alert">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert bad" role="alert">
        @if ($errors->count() === 1)
            {{ $errors->first() }}
        @else
            Please fix these:
            <ul style="margin:6px 0 0 18px;padding:0">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        @endif
    </div>
@endif
