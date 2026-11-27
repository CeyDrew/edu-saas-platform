<?php

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Role;
use App\Domain\Learning\Models\Enrollment;
use App\Domain\Notifications\OrderConfirmed;
use App\Domain\Sales\Models\Coupon;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Payments\FakeGateway;
use App\Domain\Sales\Payments\PaymentManager;
use App\Domain\Sales\Services\PaymentService;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->brand = Tenant::factory()->create(['slug' => 'kodakademi', 'commission_rate' => 10]);
    $this->headers = ['X-Tenant' => 'kodakademi'];
    $this->student = userWithRole(Role::Student, $this->brand);
    $this->course = asTenant($this->brand, fn () => Course::factory()->published()->paid(50000)->create(['slug' => 'laravel']));
    $this->billing = [
        'type' => 'individual', 'name' => 'Ayşe Yılmaz', 'tckn' => '10000000146',
        'address' => 'Moda Cad. 1', 'city' => 'İstanbul',
    ];
    Sanctum::actingAs($this->student);
    Notification::fake();
});

function checkout(array $overrides = []): TestResponse
{
    return test()->postJson('/api/checkout', $overrides + ['course' => 'laravel', 'billing' => test()->billing], test()->headers);
}

function payFake(string $token, bool $success = true): TestResponse
{
    return test()->postJson('/api/payments/fake/complete', ['token' => $token, 'success' => $success], test()->headers);
}

it('sells a course end to end through the hosted payment page', function () {
    $response = checkout()->assertCreated()->assertJsonPath('data.order.status', 'pending');
    expect($response->json('data.payment_page_url'))->toStartWith('http://kodakademi.localhost:3300/odeme/sahte?token=');

    $order = Order::query()->where('number', $response->json('data.order.number'))->firstOrFail();
    payFake($order->payment_token)->assertOk()->assertJsonPath('data.status', 'paid');

    $order->refresh();
    expect($order->isPaid())->toBeTrue()
        ->and($order->platform_fee)->toBe(5000)
        ->and(Enrollment::query()->where('user_id', $this->student->id)->where('course_id', $this->course->id)->exists())->toBeTrue()
        ->and(Payment::query()->where('order_id', $order->id)->value('status'))->toBe('success')
        ->and(Invoice::query()->where('order_id', $order->id)->value('kind'))->toBe('e-arsiv');

    Notification::assertSentTo($this->student, OrderConfirmed::class, fn ($n, $channels) => in_array('mail', $channels, true));
});

it('marks failed payments without enrolling', function () {
    $order = Order::query()->where('number', checkout()->json('data.order.number'))->firstOrFail();

    payFake($order->payment_token, success: false)->assertOk()->assertJsonPath('data.status', 'failed');

    expect(Enrollment::query()->count())->toBe(0);
});

it('enrolls immediately in free courses without a payment provider', function () {
    asTenant($this->brand, fn () => Course::factory()->published()->create(['slug' => 'git', 'price' => 0]));

    checkout(['course' => 'git'])
        ->assertCreated()
        ->assertJsonPath('data.order.status', 'paid')
        ->assertJsonPath('data.payment_page_url', null);

    expect(Enrollment::query()->where('user_id', $this->student->id)->value('source'))->toBe('free');
});

it('applies a coupon and counts its use only after payment', function () {
    $coupon = asTenant($this->brand, fn () => Coupon::factory()->create(['code' => 'YAZ20', 'type' => 'percent', 'value' => 20]));

    $this->postJson('/api/checkout/quote', ['course' => 'laravel', 'coupon' => 'yaz20'], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.discount', 10000)
        ->assertJsonPath('data.total', 40000);

    $order = Order::query()->where('number', checkout(['coupon' => 'YAZ20'])->json('data.order.number'))->firstOrFail();
    expect($order->total)->toBe(40000)->and($coupon->fresh()->used_count)->toBe(0);

    payFake($order->payment_token);
    expect($coupon->fresh()->used_count)->toBe(1);
});

it('rejects unusable coupons', function (array $state, string $message) {
    asTenant($this->brand, fn () => Coupon::factory()->create(['code' => 'KOD'] + $state));

    $this->postJson('/api/checkout/quote', ['course' => 'laravel', 'coupon' => 'KOD'], $this->headers)
        ->assertJsonValidationErrors(['coupon' => $message]);
})->with([
    'expired' => [['expires_at' => now()->subDay()], 'Kuponun süresi dolmuş.'],
    'used up' => [['max_uses' => 1, 'used_count' => 1], 'Kupon kullanım limiti doldu.'],
    'inactive' => [['is_active' => false], 'Kupon aktif değil.'],
    'minimum' => [['min_amount' => 100000], 'Sepet tutarı bu kupon için yetersiz.'],
]);

it('does not accept another brands coupon', function () {
    asTenant(Tenant::factory()->create(), fn () => Coupon::factory()->create(['code' => 'BASKA']));

    $this->postJson('/api/checkout/quote', ['course' => 'laravel', 'coupon' => 'BASKA'], $this->headers)
        ->assertJsonValidationErrors(['coupon' => 'Kupon bulunamadı.']);
});

it('validates invoice details with TCKN and VKN checksums', function () {
    checkout(['billing' => ['tckn' => '12345678901'] + $this->billing])->assertJsonValidationErrors('billing.tckn');

    checkout(['billing' => ['type' => 'corporate', 'company' => 'Acme A.Ş.', 'vkn' => '1234567891', 'tax_office' => 'Kadıköy', 'address' => 'X', 'city' => 'İstanbul']])
        ->assertJsonValidationErrors('billing.vkn');

    checkout(['billing' => ['type' => 'corporate', 'company' => 'Acme A.Ş.', 'vkn' => '1234567890', 'address' => 'X', 'city' => 'İstanbul']])
        ->assertJsonValidationErrors('billing.tax_office');
});

it('issues an e-Fatura for companies', function () {
    $order = Order::query()->where('number', checkout(['billing' => [
        'type' => 'corporate', 'company' => 'Acme Eğitim A.Ş.', 'vkn' => '1234567890', 'tax_office' => 'Kadıköy',
        'address' => 'Moda Cad. 1', 'city' => 'İstanbul',
    ]])->assertCreated()->json('data.order.number'))->firstOrFail();

    payFake($order->payment_token);

    expect(Invoice::query()->where('order_id', $order->id)->value('kind'))->toBe('e-fatura')
        ->and($order->fresh()->billing['name'])->toBe('Acme Eğitim A.Ş.');
});

it('refuses to sell a course the student already owns', function () {
    asTenant($this->brand, fn () => Enrollment::factory()->create(['user_id' => $this->student->id, 'course_id' => $this->course->id]));

    checkout()->assertJsonValidationErrors('course');
});

it('never shows a student another students order', function () {
    $other = asTenant($this->brand, fn () => Order::factory()->create());

    $this->getJson("/api/me/orders/{$other->number}", $this->headers)->assertNotFound();
});

it('rejects a paid amount that does not match the order', function () {
    $order = Order::query()->where('number', checkout()->json('data.order.number'))->firstOrFail();
    $order->forceFill(['total' => 1])->save();

    /** @var FakeGateway $gateway */
    $gateway = app(PaymentManager::class)->driver('fake');

    expect(fn () => app(PaymentService::class)->handleWebhook('fake', $gateway->complete($order->payment_token, true)))
        ->toThrow(RuntimeException::class);
    expect($order->fresh()->isPaid())->toBeFalse();
});
