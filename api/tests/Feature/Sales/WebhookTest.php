<?php

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Role;
use App\Domain\Learning\Models\Enrollment;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\WebhookEvent;
use App\Domain\Sales\Payments\FakeGateway;
use App\Domain\Sales\Payments\PaymentManager;
use App\Domain\Sales\Services\CheckoutService;
use App\Domain\Sales\Services\PaymentService;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Notification::fake();
    $this->brand = Tenant::factory()->create();
    $this->student = userWithRole(Role::Student, $this->brand);

    $this->order = asTenant($this->brand, function () {
        $course = Course::factory()->published()->paid(30000)->create();

        return app(CheckoutService::class)->start($this->student, $course, null, ['type' => 'individual'])['order'];
    });

    /** @var FakeGateway $gateway */
    $gateway = app(PaymentManager::class)->driver('fake');
    $this->gateway = $gateway;
});

/**
 * Post a signed fake-provider notification over HTTP, with no brand header, like a provider would.
 */
function deliver(Request $signed): TestResponse
{
    return test()->call('POST', '/api/webhooks/payments/fake', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_FAKE_SIGNATURE' => $signed->header('X-Fake-Signature'),
    ], $signed->getContent());
}

it('processes a notification once even when the provider repeats it', function () {
    $signed = $this->gateway->complete($this->order->payment_token, true);

    deliver($signed)->assertOk()->assertJsonPath('result', 'processed');
    deliver($signed)->assertOk()->assertJsonPath('result', 'duplicate');
    deliver($signed)->assertOk()->assertJsonPath('result', 'duplicate');

    expect(WebhookEvent::query()->count())->toBe(1)
        ->and(Payment::acrossTenants()->count())->toBe(1)
        ->and(Enrollment::acrossTenants()->count())->toBe(1)
        ->and(Order::acrossTenants()->whereKey($this->order->id)->value('status'))->toBe(OrderStatus::Paid);
});

it('is safe when the callback and the webhook both confirm the same payment', function () {
    $signed = $this->gateway->complete($this->order->payment_token, true);

    app(PaymentService::class)->confirm('fake', $this->order->payment_token);
    deliver($signed)->assertOk();

    expect(Payment::acrossTenants()->count())->toBe(1);
});

it('rejects notifications with a bad signature', function () {
    $signed = $this->gateway->complete($this->order->payment_token, true);

    $this->call('POST', '/api/webhooks/payments/fake', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_FAKE_SIGNATURE' => 'forged',
    ], $signed->getContent())->assertUnauthorized();

    expect(Order::acrossTenants()->whereKey($this->order->id)->value('status'))->toBe(OrderStatus::Pending);
});

it('ignores notifications for unknown checkouts', function () {
    deliver($this->gateway->signedRequest(['event_id' => 'x1', 'token' => 'nope', 'status' => 'success']))
        ->assertOk()
        ->assertJsonPath('result', 'ignored');
});
