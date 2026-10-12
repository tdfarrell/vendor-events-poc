<?php

namespace App\Services\Payments;

use App\Tenancy\Tenancy;

/** Sends payers to a built-in test checkout page with Pay and Cancel buttons. */
class FakeGateway implements PaymentGateway
{
    public function checkoutUrl(string $kind, string $token, int $amountCents, int $platformFeeCents, string $description): string
    {
        return Tenancy::current()->url("/test-checkout/{$kind}/{$token}");
    }
}
