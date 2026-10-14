<?php

namespace App\Http\Controllers\Directory;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Vendor;
use App\Models\Venue;
use App\Models\VendorApplication;
use App\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The statewide directory on the root domain. This is the only place that
 * reads across organizers, and it only shows published events, listed
 * off-platform events, and vendors who chose to be listed.
 */
class DirectoryController extends Controller
{
    public function events(Request $request)
    {
        $f = [
            'q' => trim((string) $request->query('q', '')),
            'type' => array_key_exists((string) $request->query('type'), config('event_types')) ? (string) $request->query('type') : '',
            'town' => trim((string) $request->query('town', '')),
            'when' => in_array($request->query('when'), ['today', 'weekend', 'month'], true) ? (string) $request->query('when') : '',
            'booking' => $request->boolean('booking'),
            'tickets' => $request->boolean('tickets'),
        ];
        $filtered = $f['q'] !== '' || $f['type'] !== '' || $f['town'] !== '' || $f['when'] !== '' || $f['booking'] || $f['tickets'];
        [$from, $to] = $this->dateRange($f['when']);

        $events = Tenancy::acrossOrganizers(function () use ($f, $from, $to) {
            $q = Event::query()->with('organizer')->published()->upcoming()
                ->withCount(['applications as vendor_count' => fn ($q) => $q->where('status', 'paid')]);
            $this->applyCommon($q, $f, $from, $to);
            if ($f['booking']) {
                $q->where('applications_open', true);
            }
            if ($f['tickets']) {
                $q->where('tickets_on_sale', true);
            }

            return $q->limit(150)->get();
        });

        // Coming-vendor avatars for the cards (a few per event).
        $faces = Tenancy::acrossOrganizers(fn () => VendorApplication::with('vendor')
            ->whereIn('event_id', $events->pluck('id'))->where('status', 'paid')->orderBy('id')->get()
            ->groupBy('event_id')->map(fn ($g) => $g->take(4)));

        $listings = ($f['booking'] || $f['tickets']) ? collect() : $this->applyCommon(Listing::query()->visible(), $f, $from, $to, true)
            ->orderByRaw('starts_on is null')->orderBy('starts_on')->limit(100)->get();

        // One list, by date: events on the platform and off-platform listings.
        $items = $events->map(fn ($e) => ['kind' => 'event', 'date' => $e->starts_on, 'model' => $e])
            ->concat($listings->map(fn ($l) => ['kind' => 'listing', 'date' => $l->starts_on, 'model' => $l]))
            ->sortBy(fn ($i) => $i['date']?->timestamp ?? PHP_INT_MAX)->values();

        $pins = $items->filter(fn ($i) => $i['model']->lat !== null)->map(function ($i) {
            $m = $i['model'];
            $isEvent = $i['kind'] === 'event';

            return [
                'id' => $i['kind'].'-'.$m->id,
                'lat' => (float) $m->lat, 'lng' => (float) $m->lng,
                'title' => $m->title,
                'when' => $m->starts_on?->format('D, M j') ?? 'Date TBA',
                'where' => trim(($m->venue_name ? $m->venue_name.', ' : '').$m->town, ', '),
                'emoji' => $m->typeConfig()['emoji'],
                'color' => $isEvent ? $m->typeConfig()['colors'][0] : '#ffffff',
                'url' => $isEvent ? $m->publicUrl() : route('listings.show', $m),
                'listing' => ! $isEvent,
            ];
        })->values();

        // Category tiles with live counts.
        $counts = Tenancy::acrossOrganizers(fn () => Event::published()->upcoming()->reorder()->selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type'));
        $listingCounts = Listing::visible()->selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type');

        $weekend = $filtered ? collect() : $events->filter(function ($e) {
            [$a, $b] = $this->dateRange('weekend');

            return $e->starts_on && $e->starts_on->between($a, $b);
        })->values();

        $spotlight = $filtered ? collect() : Tenancy::acrossOrganizers(fn () => Vendor::query()
            ->where('listed', true)->whereNotNull('slug')->where('business_name', '<>', '')
            ->whereHas('applications', fn ($q) => $q->where('status', 'paid')
                ->whereHas('event', fn ($q) => $q->published()->where('starts_on', '>=', today()->toDateString())))
            ->withCount(['applications as upcoming' => fn ($q) => $q->where('status', 'paid')
                ->whereHas('event', fn ($q) => $q->published()->where('starts_on', '>=', today()->toDateString()))])
            ->orderByDesc('upcoming')->orderBy('business_name')->limit(8)->get());

        $towns = Tenancy::acrossOrganizers(fn () => Event::published()->upcoming()->where('town', '<>', '')->reorder()->distinct()->pluck('town'))
            ->concat(Listing::visible()->where('town', '<>', '')->distinct()->pluck('town'))->unique()->sort()->values();

        $venues = $filtered ? collect() : Venue::public()->with('rooms')->orderByRaw('organizer_id is null')->orderBy('name')->limit(4)->get();

        return view('directory.events', compact('items', 'pins', 'counts', 'listingCounts', 'weekend', 'spotlight', 'towns', 'f', 'filtered', 'faces', 'venues'));
    }

    private function applyCommon(Builder $q, array $f, ?Carbon $from, ?Carbon $to, bool $isListing = false): Builder
    {
        return $q
            ->when($f['type'] !== '', fn ($q) => $q->where('type', $f['type']))
            ->when($f['town'] !== '', fn ($q) => $q->where('town', 'like', '%'.$f['town'].'%'))
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$f['q'].'%')
                ->orWhere('description', 'like', '%'.$f['q'].'%')
                ->orWhere('venue_name', 'like', '%'.$f['q'].'%')
                ->when(! $isListing, fn ($q) => $q->orWhere('categories', 'like', '%'.$f['q'].'%'))))
            ->when($from, fn ($q) => $q->whereBetween('starts_on', [$from->toDateString(), $to->toDateString()]));
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function dateRange(string $when): array
    {
        $today = today();

        return match ($when) {
            'today' => [$today, $today],
            'weekend' => $today->isWeekend()
                ? [$today, $today->isSunday() ? $today : $today->copy()->addDay()]
                : [$today->copy()->next(Carbon::SATURDAY), $today->copy()->next(Carbon::SUNDAY)],
            'month' => [$today, $today->copy()->addDays(30)],
            default => [null, null],
        };
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
        $vendor = Vendor::with('photos')->where('slug', $slug)->firstOrFail();
        abort_unless($vendor->isPublic(), 404);
        $appearances = $vendor->upcomingAppearances();

        return view('directory.vendor', compact('vendor', 'appearances'));
    }
}
