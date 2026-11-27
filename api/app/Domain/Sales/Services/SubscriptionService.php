<?php

namespace App\Domain\Sales\Services;

use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\Enums\OrderType;
use App\Domain\Sales\Enums\SubscriptionStatus;
use App\Domain\Sales\Events\SubscriptionSuspended;
use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\Subscription;
use App\Domain\Sales\Models\SubscriptionPlan;
use App\Domain\Sales\Payments\PaymentManager;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionService
{
    public function __construct(private readonly PaymentManager $payments) {}

    public function activate(User $user, SubscriptionPlan $plan, ?string $cardToken): Subscription
    {
        $subscription = Subscription::query()->firstOrNew(['user_id' => $user->id, 'plan_id' => $plan->id]);

        $subscription->forceFill([
            'status' => SubscriptionStatus::Active,
            'current_period_end' => now()->addMonths($plan->interval_months),
            'failed_attempts' => 0,
            'next_attempt_at' => null,
            'cancelled_at' => null,
            'card_token' => $cardToken ?? $subscription->card_token,
        ])->save();

        return $subscription;
    }

    public function cancel(Subscription $subscription): void
    {
        // Access continues until the paid period ends.
        $subscription->forceFill(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()])->save();
    }

    /**
     * Charge a due subscription once. Failures are retried the next day; after the third
     * failed attempt the subscription is suspended and access is revoked.
     */
    public function renew(Subscription $subscription): bool
    {
        $subscription->loadMissing(['plan', 'user']);

        $order = Order::query()->create([
            'user_id' => $subscription->user_id,
            'number' => 'R'.now()->format('ymd').Str::upper(Str::random(8)),
            'type' => OrderType::Renewal,
            'subtotal' => $subscription->plan->price,
            'total' => $subscription->plan->price,
            'payment_provider' => $this->payments->driver()->name(),
        ]);
        $order->items()->create(['plan_id' => $subscription->plan_id, 'description' => $subscription->plan->name.' (yenileme)', 'price' => $subscription->plan->price]);
        $order->forceFill(['payment_token' => $order->number])->save();

        $result = $this->payments->driver()->chargeStoredCard($subscription, $order->load('user'));

        if ($result->successful) {
            app(PaymentService::class)->markPaid($order, $result);

            return true;
        }

        app(PaymentService::class)->markFailed($order, $result);
        $this->registerFailure($subscription);

        return false;
    }

    public function renewalPaid(Order $order): void
    {
        $item = $order->items->first();
        $subscription = Subscription::query()->where('user_id', $order->user_id)->where('plan_id', $item?->plan_id)->first();

        if ($subscription === null) {
            return;
        }

        $months = $subscription->plan->interval_months;
        // Extend from the end of the paid period, not from today, so a late retry loses no days.
        $from = $subscription->current_period_end->isFuture() ? $subscription->current_period_end : now();

        $subscription->forceFill([
            'status' => SubscriptionStatus::Active,
            'current_period_end' => $from->copy()->addMonths($months),
            'failed_attempts' => 0,
            'next_attempt_at' => null,
        ])->save();
    }

    private function registerFailure(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $attempts = $subscription->failed_attempts + 1;
            $suspend = $attempts >= (int) config('payments.renewal.max_attempts', Subscription::MAX_FAILED_ATTEMPTS);

            $subscription->forceFill([
                'failed_attempts' => $attempts,
                'status' => $suspend ? SubscriptionStatus::Suspended : SubscriptionStatus::PastDue,
                'next_attempt_at' => $suspend ? null : now()->addDays((int) config('payments.renewal.retry_after_days', 1)),
            ])->save();

            if ($suspend) {
                SubscriptionSuspended::dispatch($subscription);
            }
        });
    }

    /**
     * @return Builder<Subscription>
     */
    public static function dueQuery(): Builder
    {
        return Subscription::acrossTenants()
            ->where(function ($query) {
                $query->where('status', SubscriptionStatus::Active)->where('current_period_end', '<=', now());
            })
            ->orWhere(function ($query) {
                $query->where('status', SubscriptionStatus::PastDue)->where('next_attempt_at', '<=', now());
            });
    }

    public static function hasPendingRenewal(Subscription $subscription): bool
    {
        return Order::query()->where('user_id', $subscription->user_id)
            ->where('type', OrderType::Renewal)->where('status', OrderStatus::Pending)->exists();
    }
}
