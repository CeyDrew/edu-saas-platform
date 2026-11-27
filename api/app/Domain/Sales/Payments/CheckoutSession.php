<?php

namespace App\Domain\Sales\Payments;

final readonly class CheckoutSession
{
    public function __construct(
        public string $token,
        public ?string $paymentPageUrl = null,
        public ?string $checkoutFormContent = null,
    ) {}
}
