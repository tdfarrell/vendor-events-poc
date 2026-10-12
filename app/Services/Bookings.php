<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TableType;
use App\Models\Vendor;
use App\Models\VendorApplication;
use App\Services\Payments\PaymentGateway;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Space inventory and the vendor application lifecycle, ported from the
 * Nutmeg app's inventory.php.
 *
 * Each event picks how vendors pay (events.vendor_payment_mode):
 *   review        - pay when applying; organizer reviews; declining refunds
 *   instant       - pay when applying and the space is confirmed immediately
 *   approve_first - apply free; once approved, pay from an emailed link
 *
 * A space counts as taken when its application is:
 *   - paid (confirmed), or paid and awaiting review
 *   - in checkout, while the checkout hold lasts
 *   - approved and still inside its payment window
 */
class Bookings
{
    public function __construct(private PaymentGateway $gateway) {}

    public static function committed(int $tableTypeId, ?int $excludeApplicationId = null): int
    {
        $now = now();

        return (int) VendorApplication::query()
            ->where('table_type_id', $tableTypeId)
            ->when($excludeApplicationId, fn ($q) => $q->where('id', '<>', $excludeApplicationId))
            ->where(fn ($q) => $q->where('status', 'paid')
                ->orWhere(fn ($q) => $q->where('status', 'pending')->whereNotNull('paid_at'))
                ->orWhere(fn ($q) => $q->where('status', 'approved')->where('pay_by', '>', $now))
                ->orWhere(fn ($q) => $q->where('status', 'checkout')->where('hold_until', '>', $now)))
            ->sum('quantity');
    }

    /** Space types for an event, each with ->committed and ->available set. */
    public static function availability(Event $event)
    {
        return $event->tableTypes()->get()->each(function (TableType $t) {
            $t->committed = self::committed($t->id);
            $t->available = max(0, $t->quantity - $t->committed);
        });
    }

    public static function platformFee(int $amountCents, string $method): int
    {
        return $method === 'online' ? (int) round($amountCents * config('platform.fees.vendor_percent') / 100) : 0;
    }

    /**
     * A vendor applies. Returns [application, redirect URL or null].
     * The URL is the checkout page when the event takes payment up front.
     */
    public function apply(Event $event, TableType $tableType, array $data): array
    {
        $vendor = Vendor::findOrCreateByEmail($data['email']);
        // Fill blanks in the vendor's profile from what they typed; never overwrite.
        foreach (['business_name', 'contact_name', 'phone', 'categories'] as $field) {
            if (! $vendor->{$field} && ! empty($data[$field])) {
                $vendor->{$field} = $data[$field];
            }
        }
        $vendor->save();
        $vendor->assignSlug();

        $base = [
            'event_id' => $event->id,
            'table_type_id' => $tableType->id,
            'vendor_id' => $vendor->id,
            'quantity' => (int) $data['quantity'],
            'business_name' => $data['business_name'],
            'contact_name' => $data['contact_name'],
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'] ?? '',
            'categories' => $data['categories'] ?? '',
            'inventory_description' => $data['inventory_description'] ?? null,
            'social_link' => $data['social_link'] ?? '',
            'needs_power' => ! empty($data['needs_power']),
            'vendor_notes' => $data['vendor_notes'] ?? null,
            'amount_cents' => $tableType->price_cents * (int) $data['quantity'],
        ];

        if ($event->vendor_payment_mode === 'approve_first') {
            $app = $this->create($base + ['status' => 'pending']);
            $this->emailReceived($app);
            $this->notifyOrganizer($app);

            return [$app, null];
        }

        // Pay up front: hold the space while they check out, or waitlist if it's gone.
        $app = DB::transaction(function () use ($tableType, $base) {
            TableType::whereKey($tableType->id)->lockForUpdate()->first();
            $available = $tableType->quantity - self::committed($tableType->id);
            if ($base['quantity'] > $available) {
                return $this->create($base + ['status' => 'waitlisted']);
            }

            return $this->create($base + [
                'status' => 'checkout',
                'hold_until' => now()->addMinutes(config('platform.checkout_hold_minutes')),
            ]);
        });

        if ($app->status === 'waitlisted') {
            $this->emailWaitlisted($app);
            $this->notifyOrganizer($app);

            return [$app, null];
        }

        if ($app->amount_cents === 0) {
            $this->markPaid($app, 'free');

            return [$app->fresh(), null];
        }

        return [$app, $this->checkoutUrl($app)];
    }

    public function checkoutUrl(VendorApplication $app): string
    {
        return $this->gateway->checkoutUrl('booking', $app->pay_token, $app->amount_cents,
            self::platformFee($app->amount_cents, 'online'), $app->event->title.': '.$app->tableSummary());
    }

    /** Organizer adds a vendor who booked by text or paid cash. Returns an error message or null. */
    public function addWalkIn(Event $event, TableType $tableType, array $data, string $method): ?string
    {
        $vendor = ! empty($data['email']) ? Vendor::findOrCreateByEmail($data['email']) : null;
        if ($vendor && ! $vendor->business_name) {
            $vendor->fill(['business_name' => $data['business_name'], 'contact_name' => $data['contact_name'] ?? ''])->save();
            $vendor->assignSlug();
        }

        return DB::transaction(function () use ($event, $tableType, $data, $method, $vendor) {
            TableType::whereKey($tableType->id)->lockForUpdate()->first();
            $qty = (int) $data['quantity'];
            $available = $tableType->quantity - self::committed($tableType->id);
            if ($qty > $available) {
                return "Only {$available} {$tableType->name} left. Raise the count on the event first if you want to add more.";
            }
            $amount = $data['amount_cents'] ?? $tableType->price_cents * $qty;
            $paid = $method !== 'unpaid';
            $this->create([
                'event_id' => $event->id,
                'table_type_id' => $tableType->id,
                'vendor_id' => $vendor?->id,
                'quantity' => $qty,
                'business_name' => $data['business_name'],
                'contact_name' => $data['contact_name'] ?: $data['business_name'],
                'email' => Str::lower($data['email'] ?? ''),
                'phone' => $data['phone'] ?? '',
                'table_numbers' => $data['table_numbers'] ?? '',
                'status' => $paid ? 'paid' : 'approved',
                'amount_cents' => $amount,
                'approved_at' => now(),
                'pay_by' => $paid ? null : ($event->starts_on?->copy()->endOfDay() ?? now()->addDays(30)),
                'paid_at' => $paid ? now() : null,
                'payment_method' => $paid ? $method : null,
                'admin_note' => 'Added by the organizer.',
            ]);

            return null;
        });
    }

    /**
     * Approves an application. A vendor who already paid (review mode) is
     * confirmed. Otherwise the space is held and a payment link is emailed
     * (also used to re-send an expired link or offer a space to the waitlist).
     * Returns an error message, or null on success.
     */
    public function approve(VendorApplication $app): ?string
    {
        $confirmedPaid = false;
        $error = DB::transaction(function () use ($app, &$confirmedPaid) {
            $app = VendorApplication::whereKey($app->id)->lockForUpdate()->firstOrFail();
            if ($app->paidAwaitingReview()) {
                $app->update(['status' => 'paid', 'approved_at' => now()]);
                $confirmedPaid = true;

                return null;
            }
            if (! in_array($app->status, ['pending', 'waitlisted', 'declined', 'cancelled', 'approved', 'incomplete'], true)) {
                return $app->status === 'paid' ? 'This vendor has already paid.' : 'This vendor is in the middle of paying. Try again in a few minutes.';
            }
            $tt = TableType::whereKey($app->table_type_id)->lockForUpdate()->firstOrFail();
            $available = $tt->quantity - self::committed($tt->id, $app->id);
            if ($app->quantity > $available) {
                return sprintf('Not enough %s left: %d requested, %d available. Waitlist this vendor, change their space type, or raise the count.',
                    $tt->name, $app->quantity, max(0, $available));
            }
            $days = max(1, (int) $app->event->payment_window_days);
            $app->update([
                'status' => 'approved', 'approved_at' => now(), 'pay_by' => now()->addDays($days),
                'amount_cents' => $tt->price_cents * $app->quantity,
                'paid_at' => null, 'refunded_cents' => null, 'refunded_at' => null,
            ]);

            return null;
        });

        if ($error === null) {
            $app->refresh();
            $confirmedPaid ? $this->emailPaid($app, true) : $this->emailApproved($app);
        }

        return $error;
    }

    /**
     * Records a payment. Safe to call twice (a payment processor can notify
     * more than once). Paying at registration lands as "paid, awaiting review"
     * in review mode and confirmed otherwise; paying an approval link confirms.
     */
    public function markPaid(VendorApplication $app, string $method, ?string $reference = null): bool
    {
        $newStatus = null;
        $wasRegistration = false;
        $changed = DB::transaction(function () use ($app, $method, $reference, &$newStatus, &$wasRegistration) {
            $app = VendorApplication::whereKey($app->id)->lockForUpdate()->firstOrFail();
            if ($app->paid_at !== null || $app->status === 'paid') {
                return false;
            }
            $wasRegistration = in_array($app->status, ['checkout', 'incomplete'], true);
            $newStatus = ($wasRegistration && $app->event->vendor_payment_mode === 'review') ? 'pending' : 'paid';
            $app->update([
                'status' => $newStatus,
                'paid_at' => now(),
                'payment_method' => $method,
                'payment_reference' => $reference ?? $app->payment_reference,
                'platform_fee_cents' => self::platformFee((int) $app->amount_cents, $method),
                'hold_until' => null,
                'approved_at' => $newStatus === 'paid' ? ($app->approved_at ?? now()) : $app->approved_at,
            ]);

            return true;
        });

        if ($changed) {
            $app->refresh();
            $newStatus === 'pending' ? $this->emailPaidUnderReview($app) : $this->emailPaid($app);
            if ($wasRegistration) {
                $this->notifyOrganizer($app);
            }
        }

        return $changed;
    }

    /** An abandoned or cancelled checkout releases its space. */
    public function markCheckoutIncomplete(VendorApplication $app): void
    {
        VendorApplication::whereKey($app->id)->where('status', 'checkout')
            ->update(['status' => 'incomplete', 'hold_until' => null, 'updated_at' => now()]);
    }

    /**
     * Waitlist / decline / cancel. Declining a vendor who paid online refunds
     * them; cancelling refunds only when asked. Returns an error or null.
     */
    public function setStatus(VendorApplication $app, string $status, bool $refund = false): ?string
    {
        $paid = $app->paid_at !== null && $app->refunded_at === null;
        if ($status === 'waitlisted' && $paid) {
            return 'This vendor has already paid. Approve them, or decline to refund their payment.';
        }
        if ($status === 'declined' && $app->status === 'paid') {
            return 'This vendor is confirmed. Use Cancel instead.';
        }
        if ($paid && ($status === 'declined' || ($status === 'cancelled' && $refund))) {
            $this->refund($app);
        }
        $app->update(['status' => $status]);
        if ($status === 'waitlisted') {
            $this->emailWaitlisted($app);
        } elseif ($status === 'declined') {
            $this->emailDeclined($app);
        }

        return null;
    }

    /**
     * Refunds an online payment. The POC only records it; with Stripe this
     * would call the Refunds API on the connected account. Cash is refunded
     * by hand, so nothing is recorded.
     */
    public function refund(VendorApplication $app): void
    {
        if ($app->payment_method !== 'online' || $app->refunded_at) {
            return;
        }
        $app->update(['refunded_cents' => $app->amount_cents, 'refunded_at' => now()]);
    }

    private function create(array $attributes): VendorApplication
    {
        $app = new VendorApplication($attributes);
        $app->pay_token = Str::random(40);
        $app->save();

        return $app->load('event.organizer', 'tableType');
    }

    /* ---------------- emails ---------------- */

    private function mailVendor(VendorApplication $app, string $subject, array $lines, ?array $button = null): void
    {
        Notifier::send($app->event->organizer, $app->email, $subject, $lines, $button, 'booking', $app->event_id);
    }

    private function details(VendorApplication $app, string $amountLabel = 'Requested'): string
    {
        $lines = ['Event: '.$app->event->whenWhere(), $amountLabel.': '.$app->tableSummary()];
        if ($app->amount_cents) {
            $lines[] = 'Total: '.Money::format($app->amount_cents);
        }

        return implode("\n", $lines);
    }

    private function emailReceived(VendorApplication $app): void
    {
        $this->mailVendor($app, "We got your vendor application: {$app->event->title}", [
            "Hi {$app->contact_name},",
            "Thanks for applying to vend at {$app->event->title}. Every application is reviewed by hand, and you'll hear back within a few days.",
            $this->details($app),
            "If you're approved, you'll get a link to pay. Your spot is confirmed once payment is in.",
        ], ['label' => 'View my application', 'url' => $app->statusUrl()]);
    }

    private function emailPaidUnderReview(VendorApplication $app): void
    {
        $this->mailVendor($app, "Application and payment received: {$app->event->title}", [
            "Hi {$app->contact_name},",
            'Your payment of '.Money::format($app->amount_cents).' went through, and your '.$app->event->space().' is held while your application is reviewed.',
            $this->details($app),
            "If they can't fit you in, you'll get a full refund automatically.",
        ], ['label' => 'View my application', 'url' => $app->statusUrl()]);
    }

    private function emailApproved(VendorApplication $app): void
    {
        $this->mailVendor($app, "You're approved for {$app->event->title}: pay to confirm", [
            "Hi {$app->contact_name},",
            "Good news: {$app->business_name} is approved to vend at {$app->event->title}.",
            $this->details($app, 'Space'),
            'Your spot is held until '.$app->pay_by->format('l, F j \a\t g:i A').'. Pay before then to lock it in.',
        ], ['label' => 'Pay and confirm', 'url' => $app->statusUrl()]);
    }

    private function emailPaid(VendorApplication $app, bool $afterReview = false): void
    {
        $lines = [
            "Hi {$app->contact_name},",
            $afterReview
                ? "Your application is approved. {$app->business_name} is confirmed for {$app->event->title}."
                : "Payment received. {$app->business_name} is confirmed for {$app->event->title}.",
            $this->details($app, 'Space'),
        ];
        if ($app->event->vendor_setup) {
            $lines[] = 'Vendor setup: '.$app->event->vendor_setup;
        }
        $this->mailVendor($app, "Confirmed: your spot at {$app->event->title}", $lines,
            ['label' => 'View my booking', 'url' => $app->statusUrl()]);
    }

    private function emailWaitlisted(VendorApplication $app): void
    {
        $this->mailVendor($app, "You're on the waitlist for {$app->event->title}", [
            "Hi {$app->contact_name},",
            "The {$app->event->space(true)} you asked for are full right now, so {$app->business_name} is on the waitlist.",
            "If one opens up, you'll get a link to pay and confirm it. Nothing to do in the meantime.",
        ]);
    }

    private function emailDeclined(VendorApplication $app): void
    {
        $lines = [
            "Hi {$app->contact_name},",
            "Thanks for applying to vend at {$app->event->title}. Unfortunately there isn't room for {$app->business_name} at this event.",
        ];
        if ($app->refunded_at) {
            $lines[] = 'Your payment of '.Money::format($app->refunded_cents).' has been refunded in full.';
        }
        $this->mailVendor($app, "Your application for {$app->event->title}", $lines);
    }

    private function notifyOrganizer(VendorApplication $app): void
    {
        $organizer = $app->event->organizer;
        if (! $organizer->contact_email) {
            return;
        }
        $subject = match (true) {
            $app->status === 'paid' => "New vendor booked: {$app->business_name}",
            $app->paidAwaitingReview() => "Vendor paid, needs review: {$app->business_name}",
            $app->status === 'waitlisted' => "Vendor joined the waitlist: {$app->business_name}",
            default => "New vendor application: {$app->business_name}",
        };
        Notifier::send($organizer, $organizer->contact_email, $subject, [
            "{$app->business_name} ({$app->contact_name}, {$app->email}) for {$app->event->title}.",
            $this->details($app),
        ], ['label' => 'Open in admin', 'url' => $organizer->url('/admin/applications/'.$app->id)], 'organizer', $app->event_id);
    }
}
