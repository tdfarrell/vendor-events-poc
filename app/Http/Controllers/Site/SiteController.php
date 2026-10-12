<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Bookings;
use App\Services\FloorPlans;
use App\Services\Ticketing;
use App\Support\Poster;
use Illuminate\Support\Facades\Auth;

class SiteController extends Controller
{
    public function home()
    {
        $events = Event::published()->upcoming()->withCount(['applications as vendor_count' => fn ($q) => $q->where('status', 'paid')])->get();
        $past = Event::published()->whereNotNull('starts_on')->where('starts_on', '<', today()->toDateString())
            ->orderByDesc('starts_on')->limit(6)->get();

        return view('site.home', compact('events', 'past'));
    }

    /** Draft and archived events are visible only to the organizer's own staff, as a preview. */
    private function visible(Event $event): bool
    {
        abort_if(! $event->isPublic() && ! Auth::check(), 404);

        return ! $event->isPublic();
    }

    public function event(Event $event)
    {
        $preview = $this->visible($event);
        $event->load('photos', 'floorPlan', 'organizer');

        $spaces = Bookings::availability($event);
        $tickets = Ticketing::selling($event) ? Ticketing::availability($event) : collect();
        $comingVendors = $event->applications()->with('vendor')->where('status', 'paid')
            ->orderBy('business_name')->get()
            ->filter(fn ($a) => ! $a->vendor || $a->vendor->listed)->values();

        return view('site.event', [
            'event' => $event,
            'spaces' => $spaces,
            'spacesLeft' => $spaces->sum('available'),
            'tickets' => $tickets,
            'ticketFrom' => $tickets->where('available', '>', 0)->min('price_cents'),
            'comingVendors' => $comingVendors,
            'acceptingVendors' => self::acceptingVendors($event),
            'hasMap' => FloorPlans::isPublic($event),
            'preview' => $preview,
        ]);
    }

    public function map(Event $event)
    {
        $preview = $this->visible($event);
        $plan = $event->floorPlan;
        abort_if(! $plan || (! $plan->is_public && ! Auth::check()), 404, "This event's floor map isn't posted yet.");

        return view('site.map', [
            'event' => $event,
            'data' => FloorPlans::publicData($event, $plan, self::acceptingVendors($event)),
            'preview' => $preview || ! $plan->is_public,
        ]);
    }

    /** Link-preview image for Facebook, texts and the like. */
    public function shareImage(Event $event)
    {
        $this->visible($event);
        $file = Poster::png($event->type, $event->id, $event->title,
            $event->starts_on?->format('l, F j') ?? 'Date to be announced',
            collect([$event->venue_name, $event->town])->filter()->join(', ').'  ·  '.$event->organizer->name,
            'e'.$event->id.'-'.$event->updated_at?->timestamp);

        return response()->file($file, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    /** Add-to-calendar file (all-day event on the event's date). */
    public function calendar(Event $event)
    {
        $this->visible($event);
        abort_unless($event->starts_on, 404);
        $esc = fn (string $s) => addcslashes(str_replace(["\r\n", "\n"], '\\n', $s), ',;\\');
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//'.config('platform.name').'//Events//EN', 'CALSCALE:GREGORIAN', 'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@'.config('platform.domain'),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$event->starts_on->format('Ymd'),
            'DTEND;VALUE=DATE:'.$event->starts_on->copy()->addDay()->format('Ymd'),
            'SUMMARY:'.$esc($event->title),
            'LOCATION:'.$esc(trim($event->venue_name.', '.$event->fullAddress(), ', ')),
            'DESCRIPTION:'.$esc(trim(($event->hours ? $event->hours."\n" : '').$event->publicUrl())),
            'URL:'.$event->publicUrl(),
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$event->slug.'.ics"',
        ]);
    }

    public static function acceptingVendors(Event $event): bool
    {
        return $event->isPublic() && $event->applications_open && $event->notice !== 'cancelled'
            && (! $event->starts_on || ! $event->starts_on->endOfDay()->isPast());
    }
}
