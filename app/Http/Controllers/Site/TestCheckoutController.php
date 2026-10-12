<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\TicketOrder;
use App\Models\VendorApplication;
use App\Services\Bookings;
use App\Services\Ticketing;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Stand-in for Stripe Checkout. Shows the amount and the platform fee, then
 * marks the payment done (or cancelled) the same way a Stripe webhook would.
 */
class TestCheckoutController extends Controller
{
    public function show(string $kind, string $token)
    {
        [$amount, $fee, $label, $backUrl] = $this->describe($kind, $token);

        return view('checkout.test', compact('kind', 'token', 'amount', 'fee', 'label', 'backUrl'));
    }

    public function complete(Request $request, string $kind, string $token, Bookings $bookings, Ticketing $ticketing)
    {
        $pay = $request->input('action') === 'pay';
        $reference = 'test_'.Str::lower(Str::random(14));

        if ($kind === 'booking') {
            $app = VendorApplication::where('pay_token', $token)->firstOrFail();
            if ($pay) {
                $bookings->markPaid($app, 'online', $reference);
            } else {
                $bookings->markCheckoutIncomplete($app);
            }

            return redirect()->route('site.booking', $app)
                ->with($pay ? 'status' : 'error', $pay ? 'Payment received.' : 'Payment cancelled. Your space was not held.');
        }

        $order = TicketOrder::where('order_token', $token)->firstOrFail();
        if ($pay) {
            $ticketing->fulfill($order, 'online', $reference);

            return redirect()->route('site.order', $order)->with('status', 'Payment received.');
        }
        $ticketing->expire($order);

        return redirect()->route('site.tickets', $order->event)->with('error', 'Payment cancelled. No tickets were bought.');
    }

    private function describe(string $kind, string $token): array
    {
        if ($kind === 'booking') {
            $app = VendorApplication::with('event', 'tableType')->where('pay_token', $token)->firstOrFail();
            abort_if($app->paid_at, 410, 'This has already been paid.');

            return [$app->amount_cents, Bookings::platformFee($app->amount_cents, 'online'),
                $app->event->title.' · '.$app->tableSummary().' · '.$app->business_name, route('site.booking', $app)];
        }
        abort_unless($kind === 'order', 404);
        $order = TicketOrder::with('event', 'items.ticketType')->where('order_token', $token)->firstOrFail();
        abort_if($order->status === 'paid', 410, 'This order is already paid.');

        return [$order->total_cents, $order->fee_cents,
            $order->event->title.' · '.$order->items->sum('quantity').' tickets', route('site.tickets', $order->event)];
    }
}
