<?php

namespace App\Domain\Sales\Jobs;

use App\Domain\Sales\Enums\SubscriptionStatus;
use App\Domain\Sales\Models\Subscription;
use App\Domain\Sales\Services\SubscriptionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One renewal attempt. Unique per subscription so an overlapping scheduler run cannot
 * charge the same card twice.
 */
class RenewSubscription implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $subscriptionId)
    {
        $this->onQueue('billing');
    }

    public function uniqueId(): string
    {
        return (string) $this->subscriptionId;
    }

    public function handle(SubscriptionService $subscriptions): void
    {
        $subscription = Subscription::query()->find($this->subscriptionId);

        if ($subscription === null || ! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
            return;
        }

        if ($subscription->status === SubscriptionStatus::Active && $subscription->current_period_end->isFuture()) {
            return;
        }

        $subscriptions->renew($subscription);
    }
}
