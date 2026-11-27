<?php

namespace App\Domain\Sales\Payments;

use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * iyzico Checkout Form (sandbox by default). Auth uses the IYZWSv2 HMAC scheme and
 * notifications are verified with the X-IYZ-SIGNATURE-V3 header.
 */
class IyzicoGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $secretKey,
        private readonly string $baseUrl,
    ) {}

    public function name(): string
    {
        return 'iyzico';
    }

    public function initialize(Order $order, User $buyer, string $callbackUrl): CheckoutSession
    {
        $billing = $order->billing ?? [];
        [$firstName, $lastName] = $this->splitName((string) ($billing['name'] ?? $buyer->name));
        $price = $this->money($order->total);

        $body = $this->post('/payment/iyzipos/checkoutform/initialize/auth/ecom', [
            'locale' => 'tr',
            'conversationId' => $order->number,
            'price' => $price,
            'paidPrice' => $price,
            'currency' => 'TRY',
            'basketId' => $order->number,
            'paymentGroup' => $order->type->value === 'course' ? 'PRODUCT' : 'SUBSCRIPTION',
            'callbackUrl' => $callbackUrl,
            'enabledInstallments' => [1],
            'buyer' => [
                'id' => (string) $buyer->id,
                'name' => $firstName,
                'surname' => $lastName,
                'gsmNumber' => $buyer->phone,
                'email' => $buyer->email,
                'identityNumber' => $billing['tckn'] ?? '11111111111',
                'registrationAddress' => $billing['address'] ?? 'Türkiye',
                'ip' => request()->ip() ?? '127.0.0.1',
                'city' => $billing['city'] ?? 'İstanbul',
                'country' => 'Turkey',
            ],
            'billingAddress' => [
                'contactName' => $billing['name'] ?? $buyer->name,
                'city' => $billing['city'] ?? 'İstanbul',
                'country' => 'Turkey',
                'address' => $billing['address'] ?? 'Türkiye',
            ],
            // Items must add up to the paid price, so the discount is spread over them.
            'basketItems' => [[
                'id' => $order->number,
                'name' => Str::limit((string) $order->items->first()?->description, 100),
                'category1' => 'Eğitim',
                'itemType' => 'VIRTUAL',
                'price' => $price,
            ]],
        ]);

        return new CheckoutSession(
            token: (string) $body['token'],
            paymentPageUrl: $body['paymentPageUrl'] ?? null,
            checkoutFormContent: $body['checkoutFormContent'] ?? null,
        );
    }

    public function retrieve(string $token): PaymentResult
    {
        $body = $this->post('/payment/iyzipos/checkoutform/auth/ecom/detail', ['locale' => 'tr', 'token' => $token]);

        return new PaymentResult(
            successful: ($body['paymentStatus'] ?? null) === 'SUCCESS',
            token: $token,
            paymentId: isset($body['paymentId']) ? (string) $body['paymentId'] : null,
            paidAmount: (int) round(((float) ($body['paidPrice'] ?? 0)) * 100),
            cardToken: isset($body['cardUserKey'], $body['cardToken']) ? $body['cardUserKey'].'|'.$body['cardToken'] : null,
            failureReason: $body['errorMessage'] ?? null,
            raw: $body,
        );
    }

    public function parseWebhook(Request $request): WebhookNotification
    {
        $payload = $request->json()->all();
        $expected = $this->webhookSignature($payload);

        if (! hash_equals($expected, (string) $request->header('X-IYZ-SIGNATURE-V3'))) {
            throw new InvalidWebhookSignature('iyzico signature mismatch.');
        }

        return new WebhookNotification(
            idempotencyKey: implode(':', [$payload['iyziEventType'] ?? '', $payload['iyziPaymentId'] ?? '', $payload['status'] ?? '']),
            token: (string) ($payload['token'] ?? ''),
            payload: $payload,
        );
    }

    public function chargeStoredCard(Subscription $subscription, Order $order): PaymentResult
    {
        [$cardUserKey, $cardToken] = explode('|', (string) $subscription->card_token) + [null, null];
        $price = $this->money($order->total);

        $body = $this->post('/payment/auth', [
            'locale' => 'tr',
            'conversationId' => $order->number,
            'price' => $price,
            'paidPrice' => $price,
            'currency' => 'TRY',
            'installment' => 1,
            'basketId' => $order->number,
            'paymentChannel' => 'WEB',
            'paymentGroup' => 'SUBSCRIPTION',
            'paymentCard' => ['cardUserKey' => $cardUserKey, 'cardToken' => $cardToken],
            'buyer' => ['id' => (string) $order->user_id, 'name' => $order->user->name, 'surname' => '-', 'email' => $order->user->email,
                'identityNumber' => '11111111111', 'registrationAddress' => 'Türkiye', 'city' => 'İstanbul', 'country' => 'Turkey', 'ip' => '127.0.0.1'],
            'billingAddress' => ['contactName' => $order->user->name, 'city' => 'İstanbul', 'country' => 'Turkey', 'address' => 'Türkiye'],
            'basketItems' => [['id' => $order->number, 'name' => 'Abonelik yenileme', 'category1' => 'Eğitim', 'itemType' => 'VIRTUAL', 'price' => $price]],
        ], throw: false);

        return new PaymentResult(
            successful: ($body['status'] ?? null) === 'success',
            token: $order->number,
            paymentId: isset($body['paymentId']) ? (string) $body['paymentId'] : null,
            paidAmount: $order->total,
            failureReason: $body['errorMessage'] ?? null,
            raw: $body,
        );
    }

    /**
     * Signature of a checkout form notification, as documented by iyzico:
     * HMAC-SHA256(secretKey, secretKey + iyziEventType + iyziPaymentId + token + paymentConversationId + status), hex.
     *
     * @param  array<string, mixed>  $payload
     */
    public function webhookSignature(array $payload): string
    {
        $message = $this->secretKey
            .($payload['iyziEventType'] ?? '')
            .($payload['iyziPaymentId'] ?? '')
            .($payload['token'] ?? '')
            .($payload['paymentConversationId'] ?? '')
            .($payload['status'] ?? '');

        return hash_hmac('sha256', $message, $this->secretKey);
    }

    /**
     * IYZWSv2 authorization header for a request.
     */
    public function authorization(string $uriPath, string $body, string $randomKey): string
    {
        $signature = hash_hmac('sha256', $randomKey.$uriPath.$body, $this->secretKey);

        return 'IYZWSv2 '.base64_encode("apiKey:{$this->apiKey}&randomKey:{$randomKey}&signature:{$signature}");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $uriPath, array $payload, bool $throw = true): array
    {
        $body = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $randomKey = now()->getTimestampMs().Str::random(8);

        $response = $this->http()
            ->withHeaders([
                'Authorization' => $this->authorization($uriPath, $body, $randomKey),
                'x-iyzi-rnd' => $randomKey,
            ])
            ->withBody($body, 'application/json')
            ->post($uriPath);

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        if ($throw && ($response->failed() || ($json['status'] ?? null) !== 'success')) {
            throw new RuntimeException('iyzico: '.($json['errorMessage'] ?? $response->body()));
        }

        return $json;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)->acceptJson()->timeout(20)->retry(2, 200, throw: false);
    }

    private function money(int $kurus): string
    {
        return number_format($kurus / 100, 2, '.', '');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [$name];
        $last = count($parts) > 1 ? (string) array_pop($parts) : '-';

        return [implode(' ', $parts), $last];
    }
}
