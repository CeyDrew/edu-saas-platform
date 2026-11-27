<?php

namespace App\Domain\Sales\Payments;

final readonly class WebhookNotification
{
    /**
     * @param  string  $idempotencyKey  Identical for repeated deliveries of the same event.
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $idempotencyKey,
        public string $token,
        public array $payload,
    ) {}
}
