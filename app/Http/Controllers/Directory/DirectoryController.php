<?php

namespace App\Http\Controllers\Directory;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Vendor;
use App\Tenancy\Tenancy;
use Illuminate\Http\Request;

/**
 * The statewide directory on the root domain. This is the only place that
 * reads across organizers, and it only ever shows published events and
 * vendors who chose to be listed.
 */
class DirectoryController extends Controller
{
    public function events(Request $request)
    {
        $type = (string) $request->query('type', '');
        $town = trim((string) $request->query('town', ''));
        $when = (string) $request->query('when', '');

        $events = Tenancy::acrossOrganizers(fn () => Event::query()
            ->with('organizer')
            ->published()
            ->upcoming()
            ->when(isset(config('event_types')[$type]), fn ($q) => $q->where('type', $type))
            ->when($town !== '', fn ($q) => $q->where('town', 'like', '%'.$town.'%'))
            ->when($when === 'weekend', fn ($q) => $q->whereBetween('starts_on', [
                today()->isWeekend() ? today()->toDateString() : today()->next('Saturday')->toDateString(),
                today()->isSunday() ? today()->toDateString() : today()->next('Sunday')->toDateString(),
            ]))
            ->when($when === 'month', fn ($q) => $q->where('starts_on', '<=', today()->addDays(30)->toDateString()))
            ->limit(200)
            ->get());

        $towns = Tenancy::acrossOrganizers(fn () => Event::published()->upcoming()->where('town', '<>', '')
            ->reorder()->distinct()->orderBy('town')->pluck('town'));

        return view('directory.events', compact('events', 'type', 'town', 'when', 'towns'));
    }

    public function vendors(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $vendors = Vendor::query()
            ->where('listed', true)->whereNotNull('slug')->where('business_name', '<>', '')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('business_name', 'like', "%{$search}%")
                ->orWhere('categories', 'like', "%{$search}%")->orWhere('town', 'like', "%{$search}%")))
            ->orderBy('business_name')
            ->limit(300)
            ->get();

        return view('directory.vendors', compact('vendors', 'search'));
    }

    public function vendor(string $slug)
    {
        $vendor = Vendor::where('slug', $slug)->firstOrFail();
        abort_unless($vendor->isPublic(), 404);
        $appearances = $vendor->upcomingAppearances();

        return view('directory.vendor', compact('vendor', 'appearances'));
    }
}
