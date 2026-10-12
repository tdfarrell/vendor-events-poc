<?php

namespace App\Http\Middleware;

use App\Models\Organizer;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the current organizer from the {organizer} subdomain, or clears it on
 * the root domain. Unknown subdomains get a 404.
 */
class ResolveOrganizer
{
    public function handle(Request $request, Closure $next): Response
    {
        Tenancy::forget();

        $route = $request->route();
        $slug = $route?->parameter('organizer');

        if ($slug !== null) {
            $organizer = Organizer::where('slug', strtolower((string) $slug))->first();
            abort_unless($organizer, 404, 'There is no organizer at this address.');
            Tenancy::set($organizer);
            URL::defaults(['organizer' => $organizer->slug]);
            View::share('organizer', $organizer);
            // Controllers don't need the subdomain as an argument.
            $route->forgetParameter('organizer');
        } else {
            View::share('organizer', null);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        Tenancy::forget();
    }
}
