<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingClaim;
use App\Services\Geocoder;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Seeding the directory with events run elsewhere, and handling claims on them. */
class ListingController extends Controller
{
    public function create()
    {
        return view('platform.listing-form', ['listing' => new Listing(['type' => 'card_show', 'status' => 'listed'])]);
    }

    public function store(Request $request)
    {
        $listing = new Listing($this->validated($request));
        $this->locate($listing);
        $listing->save();

        return redirect()->route('platform.dashboard')->with('status', "Listed “{$listing->title}”.");
    }

    public function edit(Listing $listing)
    {
        return view('platform.listing-form', compact('listing'));
    }

    public function update(Request $request, Listing $listing)
    {
        $listing->fill($this->validated($request));
        if ($listing->isDirty(['venue_name', 'venue_address', 'town']) || $listing->lat === null) {
            $this->locate($listing);
        }
        $listing->save();

        return redirect()->route('platform.dashboard')->with('status', 'Listing saved.');
    }

    public function decideClaim(Request $request, ListingClaim $claim)
    {
        $approve = $request->input('decision') === 'approve';
        $claim->update(['status' => $approve ? 'approved' : 'rejected']);
        if ($approve) {
            $claim->listing->update(['status' => 'claimed']);
            Notifier::send(null, $claim->email, 'About '.$claim->listing->title, [
                "Hi {$claim->name},",
                "Thanks for claiming {$claim->listing->title}. Sign up as an organizer with this email and your event can take vendor bookings and sell tickets on ".config('platform.name').'.',
            ], ['label' => 'Set up your organizer account', 'url' => route('signup')], 'claim');
        }

        return back()->with('status', $approve ? 'Claim approved and emailed. The listing is hidden until they publish their event.' : 'Claim rejected.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('event_types')))],
            'title' => ['required', 'string', 'max:200'],
            'starts_on' => ['nullable', 'date'],
            'hours' => ['nullable', 'string', 'max:80'],
            'venue_name' => ['nullable', 'string', 'max:200'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'town' => ['nullable', 'string', 'max:100'],
            'host_name' => ['nullable', 'string', 'max:150'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in(['listed', 'claimed', 'hidden'])],
        ]);
        foreach (['hours', 'venue_name', 'venue_address', 'town', 'host_name', 'source_url'] as $k) {
            $data[$k] = $data[$k] ?? '';
        }

        return $data;
    }

    private function locate(Listing $listing): void
    {
        if ($hit = Geocoder::locate($listing->venue_name, $listing->venue_address, $listing->town)) {
            [$listing->lat, $listing->lng] = $hit;
        }
    }
}
