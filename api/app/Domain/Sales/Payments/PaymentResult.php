<?php

namespace App\Domain\Sales\Payments;

final readonly class PaymentResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $successful,
        public string $token,
        public ?string $paymentId,
        public int $paidAmount,
        public ?string $cardToken = null,
        public ?string $failureReason = null,
        public array $raw = [],
    ) {}
}
