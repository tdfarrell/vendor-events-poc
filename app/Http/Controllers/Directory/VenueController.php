<?php

namespace App\Http\Controllers\Directory;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Venue;
use App\Models\VenueInquiry;
use App\Services\Notifier;
use App\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Venues in the directory: browse rooms by town, size and amenity, send a
 * rental request, or claim an unclaimed listing.
 */
class VenueController extends Controller
{
    public function index(Request $request)
    {
        $f = [
            'q' => trim((string) $request->query('q', '')),
            'town' => trim((string) $request->query('town', '')),
            'kind' => array_key_exists((string) $request->query('kind'), config('venues.kinds')) ? (string) $request->query('kind') : '',
            'guests' => max(0, min(5000, (int) $request->query('guests', 0))),
            'amenity' => array_key_exists((string) $request->query('amenity'), config('venues.amenities')) ? (string) $request->query('amenity') : '',
        ];
        $filtered = $f['q'] !== '' || $f['town'] !== '' || $f['kind'] !== '' || $f['guests'] > 0 || $f['amenity'] !== '';

        $venues = $this->filtered(Venue::query()->public()->with('rooms'), $f)
            ->orderByRaw('organizer_id is null')->orderBy('name')->limit(200)->get();

        $pins = $venues->filter(fn ($v) => $v->lat !== null)->map(fn (Venue $v) => [
            'id' => 'venue-'.$v->id, 'lat' => $v->lat, 'lng' => $v->lng, 'title' => $v->name,
            'when' => $v->maxGuests() ? 'Up to '.number_format($v->maxGuests()).' guests' : $v->kindLabel(),
            'where' => $v->town, 'emoji' => $v->kindConfig()['emoji'], 'color' => $v->kindConfig()['colors'][0],
            'url' => route('venues.show', $v->slug), 'listing' => false,
        ])->values();

        $counts = Venue::public()->selectRaw('kind, count(*) c')->groupBy('kind')->pluck('c', 'kind');
        $towns = Venue::public()->where('town', '<>', '')->distinct()->orderBy('town')->pluck('town');

        return view('directory.venues', compact('venues', 'pins', 'counts', 'towns', 'f', 'filtered'));
    }

    private function filtered(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['kind'] !== '', fn ($q) => $q->where('kind', $f['kind']))
            ->when($f['town'] !== '', fn ($q) => $q->where('town', 'like', '%'.$f['town'].'%'))
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$f['q'].'%')
                ->orWhere('about', 'like', '%'.$f['q'].'%')
                ->orWhereHas('rooms', fn ($r) => $r->where('name', 'like', '%'.$f['q'].'%'))))
            ->when($f['guests'] > 0, fn ($q) => $q->whereHas('rooms', fn ($r) => $r->where(fn ($r) => $r
                ->where('seated', '>=', $f['guests'])->orWhere('standing', '>=', $f['guests']))))
            ->when($f['amenity'] !== '', fn ($q) => $q->whereHas('rooms', fn ($r) => $r->where('amenities', 'like', '%"'.$f['amenity'].'"%')));
    }

    public function show(string $slug)
    {
        $venue = Venue::public()->with(['rooms', 'organizer'])->where('slug', $slug)->firstOrFail();
        $events = Tenancy::acrossOrganizers(fn () => Event::with('organizer')->published()->upcoming()
            ->where('venue_id', $venue->id)->limit(6)->get());

        return view('directory.venue', compact('venue', 'events'));
    }

    public function inquire(Request $request, string $slug)
    {
        $venue = Venue::public()->with(['rooms', 'organizer'])->where('slug', $slug)->firstOrFail();
        if ($request->filled('company_website')) {
            return back()->with('error', 'Something went wrong. Please try again.');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'event_kind' => ['required', Rule::in(array_keys(config('venues.event_kinds')))],
            'event_date' => ['nullable', 'date', 'after_or_equal:today'],
            'date_flexible' => ['nullable', 'boolean'],
            'time_range' => ['nullable', 'string', 'max:80'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'venue_room_id' => ['nullable', Rule::in($venue->rooms->pluck('id')->all())],
            'budget' => ['nullable', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:3000'],
        ], ['event_date.after_or_equal' => 'Pick a date that hasn\'t passed yet.']);

        $inquiry = new VenueInquiry([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? '',
            'event_kind' => $data['event_kind'], 'event_date' => $data['event_date'] ?? null,
            'date_flexible' => $request->boolean('date_flexible'), 'time_range' => $data['time_range'] ?? '',
            'guests' => $data['guests'] ?? null, 'venue_room_id' => $data['venue_room_id'] ?? null,
            'budget' => $data['budget'] ?? '', 'message' => $data['message'] ?? '',
        ]);
        $inquiry->venue_id = $venue->id;
        $inquiry->save();

        $this->notify($venue, $inquiry->load('room'));

        return redirect()->route('venues.show', $venue->slug)
            ->with('status', "Request sent. {$venue->name} will reply to {$data['email']}.");
    }

    private function notify(Venue $venue, VenueInquiry $i): void
    {
        $platform = config('platform.name');
        $details = collect([
            'Event: '.$i->kindLabel(),
            'When: '.$i->whenText(),
            $i->guests ? 'Guests: '.$i->guests : null,
            $i->room ? 'Room: '.$i->room->name : null,
            $i->budget ? 'Budget: '.$i->budget : null,
            'From: '.$i->name.' · '.$i->email.($i->phone ? ' · '.$i->phone : ''),
        ])->filter()->join("\n");
        $message = $i->message ? "Their message:\n".$i->message : null;

        if ($to = $venue->inquiryEmail()) {
            if ($venue->isClaimed()) {
                Notifier::send($venue->organizer, $to, "Rental request: {$i->kindLabel()} on ".($i->event_date?->format('M j') ?? 'a date to be set'),
                    array_values(array_filter(["{$i->name} sent a rental request for {$venue->name}.", $details, $message, 'Reply to this email to answer them directly.'])),
                    ['label' => 'Open in your inbox', 'url' => $venue->organizer->url('/admin/inquiries/'.$i->id)], 'inquiry', null, $i->email);
            } else {
                Notifier::send(null, $to, "Rental request for {$venue->name} from {$platform}", array_values(array_filter([
                    "Someone found {$venue->name} on {$platform}, a free Connecticut directory of events and venues, and sent this request.",
                    $details, $message,
                    'Reply to this email to answer them directly. To keep your listing up to date and get requests in your own inbox, claim it for free.',
                ])), ['label' => 'Claim your free listing', 'url' => $venue->url('#claim')], 'inquiry', null, $i->email);
            }
        }

        Notifier::send($venue->organizer, $i->email, "Your request to {$venue->name}", array_values(array_filter([
            "Hi {$i->name},",
            "We sent your request to {$venue->name}. They'll reply to you at this address.",
            $details,
        ])), ['label' => 'See the venue', 'url' => $venue->url()], 'inquiry', null, $venue->inquiryEmail() ?: null);
    }

    public function claim(Request $request, string $slug)
    {
        $venue = Venue::where('status', 'listed')->whereNull('organizer_id')->where('slug', $slug)->firstOrFail();
        if ($request->filled('company_website')) {
            return back()->with('error', 'Something went wrong. Please try again.');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $venue->claims()->create(array_map(fn ($v) => $v ?? '', $data));

        if ($to = config('platform.admin_email')) {
            Notifier::send(null, $to, "Venue claim: {$venue->name}", [
                "{$data['name']} ({$data['email']}) says they manage {$venue->name}.",
                'Role: '.($data['role'] ?: 'not given').($data['note'] ? "\nNote: {$data['note']}" : ''),
            ], ['label' => 'Review claims', 'url' => route('platform.dashboard')], 'claim');
        }

        return redirect()->route('venues.show', $venue->slug)
            ->with('status', "Thanks. We'll check and email you at {$data['email']} to hand {$venue->name} over to you.");
    }
}
