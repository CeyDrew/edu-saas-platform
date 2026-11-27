<?php

namespace App\Domain\Sales\Payments;

use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\Subscription;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Local stand-in for a hosted payment page, used when no iyzico sandbox keys are set
 * and in tests. It follows the same contract: a token, a hosted page, a signed
 * notification and a server-side result lookup.
 */
class FakeGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $secret,
        private readonly string $frontendUrlPattern,
    ) {}

    public function name(): string
    {
        return 'fake';
    }

    public function initialize(Order $order, User $buyer, string $callbackUrl): CheckoutSession
    {
        $token = 'fake_'.Str::random(32);
        Cache::put($this->key($token), ['order' => $order->number, 'amount' => $order->total, 'status' => 'pending'], now()->addHour());

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->findOrFail($order->tenant_id);
        $origin = str_replace('{tenant}', $tenant->slug, $this->frontendUrlPattern);

        return new CheckoutSession($token, paymentPageUrl: "{$origin}/odeme/sahte?token={$token}");
    }

    public function retrieve(string $token): PaymentResult
    {
        $session = Cache::get($this->key($token));
        $successful = is_array($session) && $session['status'] === 'success';

        return new PaymentResult(
            successful: $successful,
            token: $token,
            paymentId: $successful ? 'fakepay_'.substr(hash('sha256', $token), 0, 16) : null,
            paidAmount: is_array($session) ? (int) $session['amount'] : 0,
            cardToken: $successful ? 'card_'.substr(hash('sha256', $token.'card'), 0, 16) : null,
            failureReason: $successful ? null : 'Kart reddedildi (test).',
            raw: is_array($session) ? $session : [],
        );
    }

    public function parseWebhook(Request $request): WebhookNotification
    {
        $expected = hash_hmac('sha256', $request->getContent(), $this->secret);

        if (! hash_equals($expected, (string) $request->header('X-Fake-Signature'))) {
            throw new InvalidWebhookSignature('Fake gateway signature mismatch.');
        }

        $payload = $request->json()->all();

        return new WebhookNotification((string) $payload['event_id'], (string) $payload['token'], $payload);
    }

    public function chargeStoredCard(Subscription $subscription, Order $order): PaymentResult
    {
        // Demo cards: a token that starts with "card_fail" is always declined.
        $declined = str_starts_with((string) $subscription->card_token, 'card_fail');

        return new PaymentResult(
            successful: ! $declined,
            token: $order->number,
            paymentId: $declined ? null : 'fakepay_'.Str::random(16),
            paidAmount: $order->total,
            failureReason: $declined ? 'Yetersiz bakiye (test).' : null,
        );
    }

    /**
     * What the hosted page does when the buyer submits the card form: store the outcome
     * and build the signed notification the provider would send.
     */
    public function complete(string $token, bool $success): Request
    {
        $session = Cache::get($this->key($token));
        abort_unless(is_array($session), 404, 'Ödeme oturumu bulunamadı.');

        $session['status'] = $success ? 'success' : 'failure';
        Cache::put($this->key($token), $session, now()->addHour());

        return $this->signedRequest([
            'event_id' => "{$token}:{$session['status']}",
            'token' => $token,
            'status' => $session['status'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function signedRequest(array $payload): Request
    {
        $body = (string) json_encode($payload);

        return Request::create('/api/webhooks/payments/fake', 'POST', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FAKE_SIGNATURE' => hash_hmac('sha256', $body, $this->secret),
        ], content: $body);
    }

    private function key(string $token): string
    {
        return "fake-checkout:{$token}";
    }
}
