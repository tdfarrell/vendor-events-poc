<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\VendorApplication;
use App\Services\Bookings;
use App\Services\VendorAuth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplyController extends Controller
{
    public function form(Event $event)
    {
        abort_unless($event->isPublic(), 404);
        $vendor = VendorAuth::current();

        return view('site.apply', [
            'event' => $event,
            'spaces' => Bookings::availability($event),
            'open' => SiteController::acceptingVendors($event),
            'prefill' => $vendor,
        ]);
    }

    public function submit(Request $request, Event $event, Bookings $bookings)
    {
        abort_unless($event->isPublic(), 404);
        if (! SiteController::acceptingVendors($event)) {
            return back()->with('error', 'Vendor applications are closed for this event.');
        }
        // Honeypot: real people leave this empty.
        if ($request->filled('company_website')) {
            return back()->with('error', 'Something went wrong. Please try again.');
        }

        $data = $request->validate([
            'table_type_id' => ['required', Rule::exists('table_types', 'id')->where('event_id', $event->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'business_name' => ['required', 'string', 'max:200'],
            'contact_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:60'],
            'inventory_description' => ['nullable', 'string', 'max:3000'],
            'social_link' => ['nullable', 'string', 'max:255'],
            'needs_power' => ['nullable', 'boolean'],
            'vendor_notes' => ['nullable', 'string', 'max:2000'],
            'agree_terms' => [$event->vendor_terms ? 'accepted' : 'nullable'],
        ]);

        $tableType = $event->tableTypes()->findOrFail($data['table_type_id']);
        if ($data['quantity'] > $tableType->max_per_vendor) {
            return back()->withInput()->withErrors(['quantity' => "You can book up to {$tableType->max_per_vendor} of these."]);
        }
        $data['categories'] = implode(', ', $data['categories'] ?? []);

        [$app, $checkoutUrl] = $bookings->apply($event, $tableType, $data);

        return $checkoutUrl ? redirect()->away($checkoutUrl) : redirect()->route('site.booking', $app);
    }

    /** The vendor's private status page (linked from every email). */
    public function status(VendorApplication $application)
    {
        $application->load('event', 'tableType');

        return view('site.booking', ['app' => $application, 'event' => $application->event]);
    }

    public function pay(VendorApplication $application, Bookings $bookings)
    {
        $canPay = $application->status === 'approved' && ! $application->holdExpired()
            || in_array($application->status, ['checkout', 'incomplete'], true) && $application->paid_at === null;
        if (! $canPay) {
            return back()->with('error', 'This booking can’t be paid right now.');
        }
        if (in_array($application->status, ['checkout', 'incomplete'], true)) {
            // Restarting an abandoned checkout: re-hold the space if it's still free.
            $tt = $application->tableType;
            if ($application->quantity > $tt->quantity - Bookings::committed($tt->id, $application->id)) {
                return back()->with('error', 'Sorry, that space was taken while you were away. Contact the organizer to join the waitlist.');
            }
            $application->update(['status' => 'checkout', 'hold_until' => now()->addMinutes(config('platform.checkout_hold_minutes'))]);
        }

        return redirect()->away($bookings->checkoutUrl($application));
    }
}
