@extends('layouts.admin')
@section('page', 'Floor plan · '.$event->title)
@section('head')<link rel="stylesheet" href="{{ asset('css/floorplan.css') }}?v=2">@endsection

@section('content')
<div class="fp-shell">
    <a class="crumb" href="{{ route('admin.events.show', $event) }}">← {{ $event->title }}</a>
    <div class="fp-head">
        <div>
            <h1 style="margin:0">Floor plan</h1>
            <div class="muted">{{ $event->title }}@if ($event->venue_name) · {{ $event->venue_name }}@endif</div>
        </div>
        <a class="btn small" href="{{ route('site.map', $event) }}" target="_blank">Preview attendee map ↗</a>
    </div>

    <div id="fpMsg" class="alert" hidden></div>
    @if ($data['isNew'])
        <div class="alert info">Start with the room's width and depth in feet, measured wall to wall, then add rows of tables. Half feet are fine.</div>
    @endif

    <div class="fp-stats" id="fpStats"></div>

    <div class="fp-bench">
        <aside class="fp-panel fp-tools" aria-label="Tools">
            <div class="fp-group">
                <h3>Room</h3>
                <div class="fp-three">
                    <label class="fp-f">Width (ft)<input type="number" id="vW" min="10" max="400" step="0.5"></label>
                    <label class="fp-f">Depth (ft)<input type="number" id="vD" min="10" max="400" step="0.5"></label>
                    <label class="fp-f">Min aisle<input type="number" id="vA" min="0" max="30" step="0.5"></label>
                </div>
                <p class="fp-hint">Min aisle is checked between rows that face each other, not between the ends of tables in a row.</p>
                <label class="fp-check"><input type="checkbox" id="vPub"> Show the map on the public event page</label>
            </div>
            <div class="fp-group">
                <h3>Add to the floor</h3>
                <div class="fp-palette" id="fpPalette"></div>
                <p class="fp-hint">Drag to move. Shift-click to select several. Keys: R rotate, D duplicate, Delete remove, arrows nudge ½ ft, Ctrl/⌘ Z undo, Ctrl/⌘ S save.</p>
            </div>
            <div class="fp-group">
                <h3>Add a row</h3>
                <div class="fp-two">
                    <label class="fp-f">Count<input type="number" id="rowN" min="1" max="60" value="6"></label>
                    <label class="fp-f">Gap (ft)<input type="number" id="rowGap" min="0" step="0.5" value="0"></label>
                </div>
                <div class="fp-two">
                    <label class="fp-f">Item<select id="rowKind"></select></label>
                    <label class="fp-f">Direction<select id="rowDir"><option value="h">Across</option><option value="v">Down</option></select></label>
                </div>
                <button type="button" class="btn small" id="rowAdd">Add row</button>
            </div>
            <div class="fp-group">
                <h3>Numbers</h3>
                <button type="button" class="btn small" id="renum">Renumber tables</button>
                <p class="fp-hint">Numbers run front to back, left to right. Vendors stay at their tables.</p>
            </div>
            <div class="fp-group"><button type="button" class="btn small danger" id="fpClear">Clear the floor</button></div>
        </aside>

        <section class="fp-stage" aria-label="Floor plan">
            <div class="fp-arm" id="fpArm" hidden><span id="fpArmText"></span><button type="button" id="fpArmCancel">Cancel</button></div>
            <div class="fp-bar">
                <div class="fp-savebar">
                    <button type="button" class="btn small fire" id="fpSave" disabled>Save</button>
                    <span class="fp-save-state" id="fpSaveState">No changes</span>
                    <button type="button" class="fp-iconbtn" id="fpUndo" aria-label="Undo" title="Undo">↶</button>
                    <button type="button" class="fp-iconbtn" id="fpRedo" aria-label="Redo" title="Redo">↷</button>
                    <button type="button" class="fp-iconbtn" id="fpPrintBtn">Print</button>
                </div>
                <div class="fp-zoom">
                    <button type="button" id="zOut" aria-label="Zoom out">−</button>
                    <button type="button" id="zFit" aria-label="Fit to width">Fit</button>
                    <button type="button" id="zIn" aria-label="Zoom in">+</button>
                </div>
            </div>
            <div class="fp-bar"><div class="fp-legend" id="fpLegend"></div></div>
            <div class="fp-canvas" id="fpCanvas"><svg id="fpMap" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Floor plan of {{ $event->venue_name ?: $event->title }}"></svg></div>
        </section>

        <aside class="fp-panel fp-side" id="fpSide" aria-label="Details"></aside>
    </div>

    <div class="fp-more">
        <div class="card" id="fpNotify" @if (! $unsent) hidden @endif>
            <h2>Email table assignments</h2>
            <p class="muted" style="margin-top:0"><span id="fpNotifyCount">{{ $unsent }}</span> confirmed vendor(s) haven't been emailed their current table number. Unpaid vendors aren't emailed.</p>
            <form method="post" action="{{ route('admin.floor-plan.notify', $event) }}">@csrf
                <button class="btn ok" name="which" value="changed">Email new and changed assignments</button>
            </form>
        </div>
        @if ($assigned)
            <form method="post" action="{{ route('admin.floor-plan.notify', $event) }}">@csrf
                <button class="btn small" name="which" value="all">Re-send table numbers to all {{ $assigned }} confirmed vendors</button>
            </form>
        @endif
        @if ($others->isNotEmpty())
            <form method="post" action="{{ route('admin.floor-plan.copy', $event) }}" class="card" style="max-width:580px">
                @csrf
                <h2>Copy a layout from another event</h2>
                <p class="muted" style="margin-top:0">Replaces this event's floor plan with a copy. Space types are matched by name. Vendor spots aren't copied.</p>
                <div class="row">
                    <select name="from_event" style="flex:1">@foreach ($others as $o)<option value="{{ $o->id }}">{{ $o->title }}{{ $o->starts_on ? ' · '.$o->starts_on->format('M j, Y') : '' }}</option>@endforeach</select>
                    <button class="btn">Copy layout</button>
                </div>
            </form>
        @endif
    </div>

    <div class="fp-print" id="fpPrint"></div>
    <div class="fp-toast" id="fpToast" hidden role="status"></div>
</div>
@endsection

@push('scripts')
<script type="application/json" id="fp-data">{!! json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
<script src="{{ asset('js/floorplan.js') }}?v=2"></script>
@endpush
