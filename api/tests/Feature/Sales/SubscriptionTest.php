<?php

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Role;
use App\Domain\Learning\EnrollmentService;
use App\Domain\Notifications\SubscriptionSuspendedNotice;
use App\Domain\Sales\Enums\SubscriptionStatus;
use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\Subscription;
use App\Domain\Sales\Models\SubscriptionPlan;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Notification::fake();
    $this->brand = Tenant::factory()->create(['slug' => 'zirve']);
    $this->headers = ['X-Tenant' => 'zirve'];
    $this->student = userWithRole(Role::Student, $this->brand);
    $this->plan = asTenant($this->brand, fn () => SubscriptionPlan::factory()->create(['price' => 19900]));
    $this->course = asTenant($this->brand, fn () => Course::factory()->published()->paid()->create(['included_in_subscription' => true]));
});

it('activates a monthly subscription that unlocks subscription courses', function () {
    Sanctum::actingAs($this->student);
    $enrollments = app(EnrollmentService::class);
    expect(asTenant($this->brand, fn () => $enrollments->canAccess($this->student, $this->course)))->toBeFalse();

    $number = $this->postJson('/api/checkout', ['plan_id' => $this->plan->id, 'billing' => [
        'type' => 'individual', 'name' => 'Ayşe', 'tckn' => '10000000146', 'address' => 'X', 'city' => 'İzmir',
    ]], $this->headers)->assertCreated()->json('data.order.number');

    $token = Order::query()->where('number', $number)->value('payment_token');
    $this->postJson('/api/payments/fake/complete', ['token' => $token, 'success' => true], $this->headers)->assertOk();

    $subscription = Subscription::query()->where('user_id', $this->student->id)->firstOrFail();
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->card_token)->toStartWith('card_')
        ->and($subscription->current_period_end->isSameDay(now()->addMonth()))->toBeTrue()
        ->and(asTenant($this->brand, fn () => $enrollments->canAccess($this->student, $this->course)))->toBeTrue();

    $this->getJson('/api/me/subscription', $this->headers)->assertJsonPath('data.status', 'active');
});

it('renews due subscriptions from the stored card', function () {
    $subscription = asTenant($this->brand, fn () => Subscription::factory()->create([
        'user_id' => $this->student->id, 'plan_id' => $this->plan->id, 'current_period_end' => now()->subHour(),
    ]));

    $this->artisan('subscriptions:renew')->expectsOutput('Queued 1 renewal(s).')->assertSuccessful();

    $subscription = Subscription::acrossTenants()->find($subscription->id);
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->current_period_end->isFuture())->toBeTrue()
        ->and(Order::acrossTenants()->where('type', 'renewal')->where('status', 'paid')->count())->toBe(1);
});

it('retries a failing renewal and suspends access after three attempts', function () {
    $subscription = asTenant($this->brand, fn () => Subscription::factory()->create([
        'user_id' => $this->student->id, 'plan_id' => $this->plan->id,
        'current_period_end' => now()->subHour(), 'card_token' => 'card_fail_demo',
    ]));
    $enrollments = app(EnrollmentService::class);

    foreach ([1 => SubscriptionStatus::PastDue, 2 => SubscriptionStatus::PastDue] as $attempt => $status) {
        $this->artisan('subscriptions:renew')->assertSuccessful();
        $subscription->refresh();
        expect($subscription->failed_attempts)->toBe($attempt)->and($subscription->status)->toBe($status);
        // Still has access while retrying.
        expect(asTenant($this->brand, fn () => $enrollments->canAccess($this->student, $this->course)))->toBeTrue();

        $this->travel(1)->days();
    }

    $this->artisan('subscriptions:renew')->assertSuccessful();
    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Suspended)
        ->and($subscription->failed_attempts)->toBe(3)
        ->and(asTenant($this->brand, fn () => $enrollments->canAccess($this->student, $this->course)))->toBeFalse()
        ->and(Order::acrossTenants()->where('type', 'renewal')->where('status', 'failed')->count())->toBe(3);

    Notification::assertSentTo($this->student, SubscriptionSuspendedNotice::class);
});

it('does not touch subscriptions that are not due', function () {
    asTenant($this->brand, fn () => Subscription::factory()->create(['user_id' => $this->student->id, 'plan_id' => $this->plan->id]));

    $this->artisan('subscriptions:renew')->expectsOutput('Queued 0 renewal(s).');
});

it('keeps access until the end of the period after cancelling', function () {
    Sanctum::actingAs($this->student);
    asTenant($this->brand, fn () => Subscription::factory()->create([
        'user_id' => $this->student->id, 'plan_id' => $this->plan->id, 'current_period_end' => now()->addDays(10),
    ]));

    $this->postJson('/api/me/subscription/cancel', [], $this->headers)->assertJsonPath('data.status', 'cancelled');

    expect(asTenant($this->brand, fn () => app(EnrollmentService::class)->canAccess($this->student, $this->course)))->toBeTrue();

    $this->travel(11)->days();
    expect(asTenant($this->brand, fn () => app(EnrollmentService::class)->canAccess($this->student, $this->course)))->toBeFalse();
});
