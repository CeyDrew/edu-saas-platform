<?php

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Role;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Payments\InvalidWebhookSignature;
use App\Domain\Sales\Payments\IyzicoGateway;
use App\Domain\Sales\Services\CheckoutService;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'payments.driver' => 'iyzico',
        'payments.iyzico.api_key' => 'sandbox-key',
        'payments.iyzico.secret_key' => 'sandbox-secret',
        'payments.iyzico.base_url' => 'https://sandbox-api.iyzipay.com',
    ]);
    $this->gateway = new IyzicoGateway('sandbox-key', 'sandbox-secret', 'https://sandbox-api.iyzipay.com');
});

it('signs requests with the IYZWSv2 scheme', function () {
    $header = $this->gateway->authorization('/payment/test', '{"a":1}', 'rnd123');

    $expectedSignature = hash_hmac('sha256', 'rnd123/payment/test{"a":1}', 'sandbox-secret');
    expect($header)->toBe('IYZWSv2 '.base64_encode("apiKey:sandbox-key&randomKey:rnd123&signature:{$expectedSignature}"));
});

it('verifies X-IYZ-SIGNATURE-V3 on notifications', function () {
    $payload = ['iyziEventType' => 'CHECKOUT_FORM_AUTH', 'iyziPaymentId' => '22416', 'token' => 'tok', 'paymentConversationId' => 'S1', 'status' => 'SUCCESS'];
    $signature = hash_hmac('sha256', 'sandbox-secret'.'CHECKOUT_FORM_AUTH'.'22416'.'tok'.'S1'.'SUCCESS', 'sandbox-secret');

    $make = fn (string $sig) => Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_IYZ_SIGNATURE_V3' => $sig], content: json_encode($payload));

    expect($this->gateway->parseWebhook($make($signature))->idempotencyKey)->toBe('CHECKOUT_FORM_AUTH:22416:SUCCESS');
    expect(fn () => $this->gateway->parseWebhook($make('bad')))->toThrow(InvalidWebhookSignature::class);
});

it('runs the checkout form flow against the iyzico API', function () {
    Notification::fake();
    Http::fake([
        '*/checkoutform/initialize/auth/ecom' => Http::response([
            'status' => 'success', 'token' => 'iyz-token', 'paymentPageUrl' => 'https://sandbox-cpp.iyzipay.com?token=iyz-token',
        ]),
        '*/checkoutform/auth/ecom/detail' => Http::response([
            'status' => 'success', 'paymentStatus' => 'SUCCESS', 'paymentId' => '9988', 'paidPrice' => '450.00',
        ]),
    ]);

    $brand = Tenant::factory()->create(['slug' => 'zirve']);
    $student = userWithRole(Role::Student, $brand, ['name' => 'Ali Veli', 'phone' => '05321234567']);

    $result = asTenant($brand, fn () => app(CheckoutService::class)->start(
        $student,
        Course::factory()->published()->paid(45000)->create(),
        null,
        ['type' => 'individual', 'name' => 'Ali Veli', 'tckn' => '10000000146', 'address' => 'Ankara', 'city' => 'Ankara'],
    ));

    expect($result['session']->paymentPageUrl)->toContain('iyz-token');

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'initialize')
        && str_starts_with($request->header('Authorization')[0], 'IYZWSv2 ')
        && $request['paidPrice'] === '450.00'
        && $request['buyer']['name'] === 'Ali'
        && $request['buyer']['surname'] === 'Veli');

    // iyzico posts the buyer back with the token; the result is fetched server side.
    $this->post('/api/payments/iyzico/callback', ['token' => 'iyz-token'])
        ->assertRedirect("http://zirve.localhost:3300/odeme/sonuc?siparis={$result['order']->number}");

    expect(Order::acrossTenants()->whereKey($result['order']->id)->value('status'))->toBe(OrderStatus::Paid);
});
