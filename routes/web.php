<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Directory;
use App\Http\Controllers\Site;
use Illuminate\Support\Facades\Route;

$domain = config('platform.domain');

/*
|--------------------------------------------------------------------------
| Directory: the root domain
|--------------------------------------------------------------------------
| Every published event and public vendor across all organizers, plus the
| vendor's own account.
*/
Route::domain($domain)->group(function () {
    Route::get('/', [Directory\DirectoryController::class, 'events'])->name('directory.events');
    Route::get('/vendors', [Directory\DirectoryController::class, 'vendors'])->name('directory.vendors');
    Route::get('/vendors/{slug}', [Directory\DirectoryController::class, 'vendor'])->name('directory.vendor');

    Route::get('/vendor/login', [Directory\VendorAccountController::class, 'loginForm'])->name('vendor.login');
    Route::post('/vendor/login', [Directory\VendorAccountController::class, 'sendLink'])->middleware('throttle:6,10');
    Route::get('/vendor/login/{token}', [Directory\VendorAccountController::class, 'confirm'])->name('vendor.login.confirm');
    Route::post('/vendor/login/{token}', [Directory\VendorAccountController::class, 'consume']);
    Route::post('/vendor/logout', [Directory\VendorAccountController::class, 'logout'])->name('vendor.logout');
    Route::get('/vendor/account', [Directory\VendorAccountController::class, 'account'])->name('vendor.account');
    Route::put('/vendor/account', [Directory\VendorAccountController::class, 'update']);
});

// www.{domain} → {domain}
Route::domain('www.'.$domain)->group(function () {
    Route::get('/{any?}', fn (string $any = '') => redirect()->away(\App\Models\Organizer::directoryUrl($any), 301))
        ->where('any', '.*');
});

/*
|--------------------------------------------------------------------------
| Organizer sites: {organizer}.{domain}
|--------------------------------------------------------------------------
| ResolveOrganizer (first in the web middleware group) sets the current organizer from the subdomain; every
| scoped query below only sees that organizer's data.
*/
Route::domain('{organizer}.'.$domain)->group(function () {
    // Public site
    Route::get('/', [Site\SiteController::class, 'home'])->name('site.home');
    Route::get('/events/{event}', [Site\SiteController::class, 'event'])->name('site.event');

    Route::get('/events/{event}/apply', [Site\ApplyController::class, 'form'])->name('site.apply');
    Route::post('/events/{event}/apply', [Site\ApplyController::class, 'submit'])->middleware('throttle:10,10');
    Route::get('/booking/{application}', [Site\ApplyController::class, 'status'])->name('site.booking');
    Route::post('/booking/{application}/pay', [Site\ApplyController::class, 'pay'])->name('site.booking.pay');

    Route::get('/events/{event}/tickets', [Site\TicketController::class, 'form'])->name('site.tickets');
    Route::post('/events/{event}/tickets', [Site\TicketController::class, 'buy'])->middleware('throttle:10,10');
    Route::get('/orders/{order}', [Site\TicketController::class, 'order'])->name('site.order');
    Route::get('/orders/{order}/qr/{code}.svg', [Site\TicketController::class, 'qr'])->name('site.order.qr');

    // Stand-in for Stripe Checkout while there's no payment account.
    Route::get('/test-checkout/{kind}/{token}', [Site\TestCheckoutController::class, 'show'])->name('checkout.test');
    Route::post('/test-checkout/{kind}/{token}', [Site\TestCheckoutController::class, 'complete']);

    // Door check-in by private link (no login, one event only)
    Route::get('/checkin/{token}', [Site\CheckinController::class, 'show'])->name('checkin');
    Route::post('/checkin/{token}/scan', [Site\CheckinController::class, 'scan'])->name('checkin.scan');
    Route::post('/checkin/{token}/vendors/{application}', [Site\CheckinController::class, 'vendor'])->name('checkin.vendor');
    Route::post('/checkin/{token}/door', [Site\CheckinController::class, 'door'])->name('checkin.door');
    Route::post('/checkin/{token}/door/undo', [Site\CheckinController::class, 'undoDoor'])->name('checkin.door.undo');

    // Organizer admin
    Route::get('/admin/login', [Admin\AuthController::class, 'form'])->name('admin.login');
    Route::post('/admin/login', [Admin\AuthController::class, 'login'])->middleware('throttle:8,10');
    Route::post('/admin/logout', [Admin\AuthController::class, 'logout'])->name('admin.logout');

    Route::prefix('admin')->name('admin.')->middleware('staff')->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('/events/create', [Admin\EventController::class, 'create'])->name('events.create');
        Route::post('/events', [Admin\EventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}', [Admin\EventController::class, 'show'])->name('events.show');
        Route::get('/events/{event}/edit', [Admin\EventController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [Admin\EventController::class, 'update'])->name('events.update');
        Route::post('/events/{event}/table-types', [Admin\EventController::class, 'addTableType'])->name('events.table-types');
        Route::put('/table-types/{tableType}', [Admin\EventController::class, 'updateTableType'])->name('table-types.update');
        Route::post('/events/{event}/ticket-types', [Admin\EventController::class, 'addTicketType'])->name('events.ticket-types');
        Route::put('/ticket-types/{ticketType}', [Admin\EventController::class, 'updateTicketType'])->name('ticket-types.update');
        Route::post('/events/{event}/walk-in', [Admin\EventController::class, 'walkIn'])->name('events.walk-in');
        Route::post('/events/{event}/comp', [Admin\EventController::class, 'comp'])->name('events.comp');
        Route::post('/events/{event}/checkin-link', [Admin\EventController::class, 'resetCheckin'])->name('events.checkin-link');
        Route::get('/events/{event}/vendors.csv', [Admin\EventController::class, 'vendorsCsv'])->name('events.vendors-csv');

        Route::get('/applications/{application:id}', [Admin\ApplicationController::class, 'show'])->name('applications.show');
        Route::post('/applications/{application:id}', [Admin\ApplicationController::class, 'act'])->name('applications.act');

        Route::get('/orders/{order:id}', [Admin\DashboardController::class, 'order'])->name('orders.show');

        Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings');
        Route::put('/settings', [Admin\SettingsController::class, 'update'])->middleware('staff:owner');
    });
});
