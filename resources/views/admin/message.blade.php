@extends('layouts.admin')
@section('page', 'Message vendors · '.$event->title)

@section('content')
<a class="crumb" href="{{ route('admin.events.show', $event) }}">← {{ $event->title }}</a>
<div class="admin-head"><div><h1>Message vendors</h1><p class="muted" style="margin:0">Load-in times, parking, weather changes. Each vendor gets their own email with a link to their booking.</p></div></div>

<div class="cols">
    <form method="post" action="{{ route('admin.messages.send', $event) }}" class="card lift">
        @csrf
        <label>Send to</label>
        <div class="tick-grid">
            @foreach ($groups as $k => $label)
                <label class="tick"><input type="checkbox" name="groups[]" value="{{ $k }}" @checked(in_array($k, old('groups', ['paid']), true))> {{ $label }} <span class="badge" style="margin-left:auto">{{ $counts[$k] ?? 0 }}</span></label>
            @endforeach
        </div>
        <div class="field"><label for="subject">Subject</label><input id="subject" type="text" name="subject" required value="{{ old('subject', 'Load-in details for '.$event->title) }}"></div>
        <div class="field"><label for="body">Message</label><textarea id="body" name="body" rows="8" required placeholder="Doors open for vendors at 8 AM. Unload at the side entrance, then move your car to the back lot.">{{ old('body') }}</textarea></div>
        <button class="btn fire">Send message</button>
    </form>

    <div class="card">
        <h3>Sent for this event</h3>
        @forelse ($sent as $batch)
            @php $first = $batch->first(); @endphp
            <div style="padding:10px 0;border-bottom:1px solid var(--line)">
                <strong>{{ $first->subject }}</strong><br>
                <small>{{ $first->created_at->format('M j, g:i A') }} · {{ $batch->count() }} {{ Str::plural('vendor', $batch->count()) }}</small>
            </div>
        @empty
            <p class="muted" style="margin:0">Nothing sent yet.</p>
        @endforelse
        <p style="margin:12px 0 0"><a href="{{ route('admin.emails', ['kind' => 'message']) }}">Full email log</a></p>
    </div>
</div>
@endsection
