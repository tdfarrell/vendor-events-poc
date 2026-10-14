<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Venue;
use App\Models\VenueClaim;
use App\Services\Geocoder;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Seeding the directory with venues, and handing them over when someone claims one. */
class VenueController extends Controller
{
    public function create()
    {
        return view('platform.venue-form', ['venue' => new Venue(['kind' => 'community_center', 'status' => 'listed']), 'room' => null]);
    }

    public function store(Request $request)
    {
        [$data, $roomData] = $this->validated($request);
        $venue = new Venue($data);
        $venue->slug = Venue::uniqueSlug($venue->name, $venue->town);
        $this->locate($venue);
        $venue->save();
        if ($roomData) {
            $venue->rooms()->create($roomData);
        }

        return redirect()->route('platform.dashboard')->with('status', "Listed “{$venue->name}”.");
    }

    public function edit(Venue $venue)
    {
        abort_if($venue->isClaimed(), 404);

        return view('platform.venue-form', ['venue' => $venue, 'room' => $venue->rooms()->first()]);
    }

    public function update(Request $request, Venue $venue)
    {
        abort_if($venue->isClaimed(), 404);
        [$data, $roomData] = $this->validated($request);
        $venue->fill($data);
        if ($venue->isDirty(['address', 'town', 'name']) || $venue->lat === null) {
            $this->locate($venue);
        }
        $venue->save();
        if ($roomData) {
            ($room = $venue->rooms()->first()) ? $room->update($roomData) : $venue->rooms()->create($roomData);
        }

        return redirect()->route('platform.dashboard')->with('status', 'Venue saved.');
    }

    public function decideClaim(Request $request, VenueClaim $claim)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'organizer_id' => ['nullable', 'integer', Rule::exists('organizers', 'id')],
        ]);
        $venue = $claim->venue;

        if ($data['decision'] === 'reject') {
            $claim->forceFill(['status' => 'rejected'])->save();

            return back()->with('status', 'Claim rejected.');
        }

        $claim->forceFill(['status' => 'approved'])->save();
        $org = isset($data['organizer_id']) ? Organizer::find($data['organizer_id']) : null;
        if ($org && ! $venue->isClaimed()) {
            $venue->forceFill(['organizer_id' => $org->id, 'status' => 'active', 'contact_email' => $venue->contact_email ?: $claim->email])->save();
            Notifier::send(null, $claim->email, "{$venue->name} is yours on ".config('platform.name'), [
                "Hi {$claim->name},",
                "{$venue->name} is now managed from {$org->name}'s admin. Update your rooms, photos and prices there, and rental requests will land in your inbox.",
            ], ['label' => 'Manage your venue', 'url' => $org->url('/admin/venue')], 'claim');

            return back()->with('status', "{$venue->name} now belongs to {$org->name}, and {$claim->email} was emailed.");
        }

        Notifier::send(null, $claim->email, 'About '.$venue->name, [
            "Hi {$claim->name},",
            "Thanks for claiming {$venue->name}. Sign up with this email and we'll move the listing into your account, so you can update rooms and prices and get rental requests in your own inbox.",
        ], ['label' => 'Create your account', 'url' => route('signup')], 'claim');

        return back()->with('status', 'Claim approved and emailed a sign-up link. Once they sign up, approve again choosing their organizer.');
    }

    /** @return array{0: array, 1: ?array} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'kind' => ['required', Rule::in(array_keys(config('venues.kinds')))],
            'address' => ['nullable', 'string', 'max:255'],
            'town' => ['required', 'string', 'max:100'],
            'about' => ['nullable', 'string', 'max:4000'],
            'rules' => ['nullable', 'string', 'max:4000'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::in(['listed', 'hidden'])],
            'room_name' => ['nullable', 'string', 'max:150'],
            'room_seated' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'room_standing' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'room_price_from' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);
        $room = filled($data['room_name'] ?? null) ? [
            'name' => $data['room_name'], 'seated' => $data['room_seated'] ?? null, 'standing' => $data['room_standing'] ?? null,
            'price_from' => $data['room_price_from'] ?? null, 'price_unit' => 'event',
        ] : null;
        $venue = collect($data)->except(['room_name', 'room_seated', 'room_standing', 'room_price_from'])->all();
        foreach (['address', 'contact_email', 'phone', 'website'] as $k) {
            $venue[$k] = $venue[$k] ?? '';
        }

        return [$venue, $room];
    }

    private function locate(Venue $venue): void
    {
        if ($hit = Geocoder::locate($venue->name, $venue->address, $venue->town)) {
            [$venue->lat, $venue->lng] = $hit;
        }
    }
}
