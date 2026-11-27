<?php

namespace App\Domain\Sales\Payments;

use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function name(): string;

    /**
     * Start a hosted checkout (card details never touch our servers).
     */
    public function initialize(Order $order, User $buyer, string $callbackUrl): CheckoutSession;

    /**
     * Ask the provider for the final result of a checkout. Never trust the browser.
     */
    public function retrieve(string $token): PaymentResult;

    /**
     * Verify the signature of a provider notification and extract what we need.
     *
     * @throws InvalidWebhookSignature
     */
    public function parseWebhook(Request $request): WebhookNotification;

    /**
     * Charge the stored card of a subscription for a renewal order.
     */
    public function chargeStoredCard(Subscription $subscription, Order $order): PaymentResult;
}
