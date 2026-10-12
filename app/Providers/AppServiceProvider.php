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
