<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Turns an address into map coordinates using OpenStreetMap's Nominatim
 * service (free; one request per save, cached). If the street address isn't
 * found it falls back to the town. Organizers can also drop the pin by hand.
 */
class Geocoder
{
    /** @return array{0: float, 1: float}|null */
    public static function locate(string $venue, string $address, string $town): ?array
    {
        if (! config('platform.geocode')) {
            return null;
        }
        $state = config('platform.state');
        $queries = array_values(array_unique(array_filter([
            trim("$address, $town, $state, USA", ', '),
            $venue && $town ? "$venue, $town, $state, USA" : null,
            $town ? "$town, $state, USA" : null,
        ], fn ($q) => $q && trim($q, ', USA') !== '')));

        foreach ($queries as $q) {
            $hit = Cache::remember('geo:'.md5($q), now()->addDays(30), function () use ($q) {
                try {
                    $res = Http::timeout(6)
                        ->withHeaders(['User-Agent' => config('platform.name').' event directory ('.config('app.url').')'])
                        ->get('https://nominatim.openstreetmap.org/search', ['q' => $q, 'format' => 'json', 'limit' => 1, 'countrycodes' => 'us']);
                    $row = $res->ok() ? ($res->json()[0] ?? null) : null;

                    return $row ? [(float) $row['lat'], (float) $row['lon']] : false;
                } catch (\Throwable) {
                    return null; // don't cache network failures
                }
            });
            if ($hit) {
                return $hit;
            }
        }

        return null;
    }
}
