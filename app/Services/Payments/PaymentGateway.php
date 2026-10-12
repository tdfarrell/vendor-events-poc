<?php

namespace App\Services\Payments;

/**
 * Starts an online payment and returns the URL to send the payer to.
 *
 * $kind is "booking" (vendor space) or "order" (tickets); $token identifies
 * it. When the payment succeeds the gateway calls Bookings::markPaid() or
 * Ticketing::fulfill() (Stripe would do this from its webhook).
 *
 * The POC uses FakeGateway. A StripeConnectGateway comes next: it would create
 * a Checkout Session on the organizer's connected account with
 * application_fee_amount set to the platform fee.
 */
interface PaymentGateway
{
    public function checkoutUrl(string $kind, string $token, int $amountCents, int $platformFeeCents, string $description): string;
}
