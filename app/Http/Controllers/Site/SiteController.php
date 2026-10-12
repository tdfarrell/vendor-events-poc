<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Bookings;
use App\Services\Ticketing;
use Illuminate\Support\Facades\Auth;

class SiteController extends Controller
{
    public function home()
    {
        $events = Event::published()->upcoming()->get();

        return view('site.home', compact('events'));
    }

    public function event(Event $event)
    {
        $preview = ! $event->isPublic();
        abort_if($preview && ! Auth::check(), 404);

        $spaces = Bookings::availability($event);
        $tickets = Ticketing::selling($event) ? Ticketing::availability($event) : collect();
        $comingVendors = $event->applications()->with('vendor')->where('status', 'paid')
            ->orderBy('business_name')->get()
            ->filter(fn ($a) => ! $a->vendor || $a->vendor->listed);

        return view('site.event', [
            'event' => $event,
            'spaces' => $spaces,
            'spacesLeft' => $spaces->sum('available'),
            'tickets' => $tickets,
            'comingVendors' => $comingVendors,
            'acceptingVendors' => self::acceptingVendors($event),
            'preview' => $preview,
        ]);
    }

    public static function acceptingVendors(Event $event): bool
    {
        return $event->isPublic() && $event->applications_open && $event->notice !== 'cancelled'
            && (! $event->starts_on || ! $event->starts_on->endOfDay()->isPast());
    }
}
