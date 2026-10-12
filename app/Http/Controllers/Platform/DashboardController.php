<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Listing;
use App\Models\ListingClaim;
use App\Models\Organizer;
use App\Services\Notifier;
use App\Tenancy\Tenancy;

/** Organizers, directory listings and claims, across the whole platform. */
class DashboardController extends Controller
{
    public function index()
    {
        $organizers = Organizer::orderByRaw("status = 'active'")->orderBy('name')->get();
        $eventCounts = Tenancy::acrossOrganizers(fn () => Event::selectRaw('organizer_id, count(*) c')->groupBy('organizer_id')->pluck('c', 'organizer_id'));
        $claims = ListingClaim::with('listing')->where('status', 'new')->latest()->get();
        $listings = Listing::orderByRaw("status = 'listed' desc")->orderBy('starts_on')->get();

        return view('platform.dashboard', compact('organizers', 'eventCounts', 'claims', 'listings'));
    }

    public function activate(Organizer $org)
    {
        $org->update(['status' => 'active']);
        if ($org->contact_email) {
            Notifier::send($org, $org->contact_email, 'Your '.config('platform.name').' site is live', [
                "{$org->name} is live at ".$org->url('/').'.',
                'Sign in to create your first event. Pick the kind of event and the usual tables, prices and categories are set up for you.',
            ], ['label' => 'Sign in', 'url' => $org->url('/admin')], 'welcome');
        }

        return back()->with('status', "{$org->name} is marked live and was emailed.");
    }
}
