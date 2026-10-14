<?php

namespace App\Providers;

use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\VendorAuth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Swap for a StripeConnectGateway once payments are set up.
        $this->app->bind(PaymentGateway::class, FakeGateway::class);
    }

    public function boot(): void
    {
        if (config('platform.scheme') === 'https') {
            URL::forceScheme('https');
        }

        // New rental requests, for the admin's Venue tab.
        View::composer('layouts.admin', function ($view) {
            $view->with('newInquiries', \App\Models\VenueInquiry::where('status', 'new')
                ->whereHas('venue', fn ($q) => $q->ownedByCurrent())->count());
        });

        View::composer('*', function ($view) {
            // Look the vendor up once per request, not once per view.
            $attrs = request()->attributes;
            if (! $attrs->has('signedInVendor')) {
                $attrs->set('signedInVendor', request()->hasSession() ? VendorAuth::current() : null);
            }
            $view->with('signedInVendor', $attrs->get('signedInVendor'));
        });
    }
}
