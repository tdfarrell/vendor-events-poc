<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\VenueInquiry;
use App\Models\VenueRoom;
use App\Services\Geocoder;
use App\Services\Notifier;
use App\Support\Uploads;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * An organizer's venues, rooms and rental inquiries. Venues aren't
 * organizer-scoped automatically, so every lookup here goes through
 * Venue::ownedByCurrent(); anything else is a 404.
 */
class VenueController extends Controller
{
    public function index()
    {
        $venues = Venue::ownedByCurrent()->with('rooms')->withCount(['inquiries as new_count' => fn ($q) => $q->where('status', 'new')])->orderBy('name')->get();
        $inquiries = $this->inquiryQuery()->with(['venue', 'room'])->latest()->limit(8)->get();

        return view('admin.venues', compact('venues', 'inquiries'));
    }

    public function create()
    {
        $org = Tenancy::current();

        return view('admin.venue-form', ['venue' => new Venue([
            'name' => $org->name, 'town' => $org->town, 'kind' => 'community_center', 'contact_email' => $org->contact_email, 'phone' => $org->phone,
        ])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $venue = new Venue($data);
        $venue->organizer_id = Tenancy::id();
        $venue->slug = Venue::uniqueSlug($venue->name, $venue->town);
        $venue->status = $request->boolean('hidden') ? 'hidden' : 'active';
        if ($err = $this->cover($request, $venue)) {
            return back()->withInput()->with('error', $err);
        }
        $this->locate($venue);
        $venue->save();

        return redirect()->route('admin.venues.edit', $venue->id)->with('status', 'Venue saved. Now add your rooms.');
    }

    public function edit(int $venue)
    {
        $venue = $this->venue($venue)->load('rooms');

        return view('admin.venue-form', compact('venue'));
    }

    public function update(Request $request, int $venue)
    {
        $venue = $this->venue($venue);
        $venue->fill($this->validated($request));
        $venue->status = $request->boolean('hidden') ? 'hidden' : 'active';
        if ($err = $this->cover($request, $venue)) {
            return back()->withInput()->with('error', $err);
        }
        if ($venue->isDirty(['address', 'town', 'name']) || $venue->lat === null) {
            $this->locate($venue);
        }
        $venue->save();

        return back()->with('status', 'Venue saved.');
    }

    public function addRoom(Request $request, int $venue)
    {
        $venue = $this->venue($venue);
        $room = new VenueRoom($this->roomData($request));
        $room->venue_id = $venue->id;
        $room->sort_order = (int) $venue->rooms()->max('sort_order') + 1;
        $room->save();

        return redirect()->to(route('admin.venues.edit', $venue->id).'#rooms')->with('status', "Added {$room->name}.");
    }

    public function updateRoom(Request $request, int $room)
    {
        $room = $this->room($room);
        $room->update($this->roomData($request));

        return redirect()->to(route('admin.venues.edit', $room->venue_id).'#room-'.$room->id)->with('status', "Saved {$room->name}.");
    }

    public function deleteRoom(int $room)
    {
        $room = $this->room($room);
        $venueId = $room->venue_id;
        $room->delete();

        return redirect()->to(route('admin.venues.edit', $venueId).'#rooms')->with('status', 'Room removed.');
    }

    public function inquiries(Request $request)
    {
        $status = array_key_exists((string) $request->query('status'), config('venues.inquiry_statuses')) ? (string) $request->query('status') : '';
        $inquiries = $this->inquiryQuery()->with(['venue', 'room'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest()->paginate(30)->withQueryString();
        $counts = $this->inquiryQuery()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return view('admin.inquiries', compact('inquiries', 'counts', 'status'));
    }

    public function inquiry(int $inquiry)
    {
        $inquiry = $this->inquiryQuery()->with(['venue', 'room'])->findOrFail($inquiry);
        $others = $this->inquiryQuery()->where('email', $inquiry->email)->whereKeyNot($inquiry->id)->latest()->get();
        $sent = \App\Models\EmailLog::where('to_email', $inquiry->email)->where('kind', 'inquiry_reply')->latest()->get();

        return view('admin.inquiry', compact('inquiry', 'others', 'sent'));
    }

    public function act(Request $request, int $inquiry)
    {
        $inquiry = $this->inquiryQuery()->with('venue')->findOrFail($inquiry);
        $data = $request->validate([
            'reply' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(array_keys(config('venues.inquiry_statuses')))],
        ]);
        $org = Tenancy::current();

        if (filled($data['reply'] ?? null)) {
            Notifier::send($org, $inquiry->email, 'Re: your request to '.$inquiry->venue->name,
                ["Hi {$inquiry->name},", trim($data['reply'])], null, 'inquiry_reply', null, $inquiry->venue->inquiryEmail() ?: null);
            $inquiry->status = in_array($inquiry->status, ['new'], true) ? 'replied' : $inquiry->status;
            $inquiry->replied_at = now();
        }
        if (filled($data['status'] ?? null)) {
            $inquiry->status = $data['status'];
        }
        $inquiry->save();

        return back()->with('status', filled($data['reply'] ?? null) ? "Reply sent to {$inquiry->email}." : 'Status updated.');
    }

    // ----------------------------------------------------------------------

    private function venue(int $id): Venue
    {
        return Venue::ownedByCurrent()->findOrFail($id);
    }

    private function room(int $id): VenueRoom
    {
        return VenueRoom::whereHas('venue', fn ($q) => $q->ownedByCurrent())->findOrFail($id);
    }

    private function inquiryQuery()
    {
        return VenueInquiry::whereHas('venue', fn ($q) => $q->ownedByCurrent());
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'kind' => ['required', Rule::in(array_keys(config('venues.kinds')))],
            'address' => ['nullable', 'string', 'max:255'],
            'town' => ['required', 'string', 'max:100'],
            'about' => ['nullable', 'string', 'max:4000'],
            'rules' => ['nullable', 'string', 'max:4000'],
            'parking_info' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'cover' => ['nullable', 'file', 'max:12288'],
        ]);
        unset($data['cover']);
        foreach (['address', 'parking_info', 'phone', 'website'] as $k) {
            $data[$k] = $data[$k] ?? '';
        }

        return $data;
    }

    private function roomData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'seated' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'standing' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'price_from' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'price_to' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'price_unit' => ['required', Rule::in(array_keys(config('venues.price_units')))],
            'description' => ['nullable', 'string', 'max:2000'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => [Rule::in(array_keys(config('venues.amenities')))],
        ]);
        $data['amenities'] = array_values($data['amenities'] ?? []);

        return $data;
    }

    private function cover(Request $request, Venue $venue): ?string
    {
        if ($request->hasFile('cover')) {
            [$path, $err] = Uploads::storeImage($request->file('cover'), 'venues', 1800);
            if ($err) {
                return $err;
            }
            Uploads::delete($venue->cover_path);
            $venue->cover_path = $path;
        } elseif ($request->boolean('remove_cover')) {
            Uploads::delete($venue->cover_path);
            $venue->cover_path = '';
        }

        return null;
    }

    private function locate(Venue $venue): void
    {
        if ($hit = Geocoder::locate($venue->name, $venue->address, $venue->town)) {
            [$venue->lat, $venue->lng] = $hit;
        }
    }
}
