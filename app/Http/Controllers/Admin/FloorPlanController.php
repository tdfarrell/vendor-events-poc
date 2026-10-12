<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\VendorApplication;
use App\Services\FloorPlans;
use Illuminate\Http\Request;

/** The floor plan builder, ported from the Nutmeg app. */
class FloorPlanController extends Controller
{
    public function edit(Event $event)
    {
        $event->load('floorPlan');
        $assigned = VendorApplication::where('event_id', $event->id)->where('status', 'paid')->where('table_numbers', '<>', '')->count();
        $others = Event::whereKeyNot($event->id)->whereHas('floorPlan')->orderByDesc('starts_on')->get(['id', 'slug', 'title', 'starts_on']);

        return view('admin.floor-plan', [
            'event' => $event,
            'data' => FloorPlans::editorData($event) + ['csrf' => csrf_token()],
            'unsent' => FloorPlans::unsentCount($event),
            'assigned' => $assigned,
            'others' => $others,
        ]);
    }

    public function save(Request $request, Event $event)
    {
        $payload = json_decode((string) $request->input('payload', ''), true);
        if (! is_array($payload)) {
            return response()->json(['ok' => false, 'messages' => ['The floor plan data was missing or damaged. Try saving again.']], 400);
        }
        [$ok, $messages] = FloorPlans::save($event, $payload);
        if (! $ok) {
            return response()->json(['ok' => false, 'messages' => $messages], 422);
        }
        $event->unsetRelation('floorPlan');
        $fresh = FloorPlans::editorData($event);

        return response()->json([
            'ok' => true, 'messages' => $messages, 'types' => $fresh['types'], 'apps' => $fresh['apps'],
            'unsent' => FloorPlans::unsentCount($event), 'savedAt' => now()->format('g:i A'),
        ]);
    }

    public function copy(Request $request, Event $event)
    {
        $from = Event::whereKey($request->input('from_event'))->firstOrFail();
        $ok = FloorPlans::copy($from, $event);

        return redirect()->route('admin.floor-plan', $event)->with($ok ? 'status' : 'error', $ok
            ? "Copied the layout from “{$from->title}”. Vendor spots weren't copied; place this event's vendors, then save."
            : "“{$from->title}” doesn't have a floor plan to copy.");
    }

    public function notify(Request $request, Event $event)
    {
        $n = FloorPlans::emailAssignments($event, $request->input('which') !== 'all');

        return redirect()->route('admin.floor-plan', $event)->with('status', $n
            ? "Emailed table assignments to $n ".str('vendor')->plural($n).'.'
            : 'Every confirmed vendor already has their current table numbers.');
    }
}
