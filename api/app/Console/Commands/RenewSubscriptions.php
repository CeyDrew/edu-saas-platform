<?php

namespace App\Console\Commands;

use App\Domain\Sales\Jobs\RenewSubscription;
use App\Domain\Sales\Models\Subscription;
use App\Domain\Sales\Services\SubscriptionService;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('subscriptions:renew')]
#[Description('Queue renewal attempts for subscriptions whose period ended or whose retry is due')]
class RenewSubscriptions extends Command
{
    public function handle(TenantContext $context): int
    {
        $count = 0;

        SubscriptionService::dueQuery()->chunkById(100, function (Collection $subscriptions) use ($context, &$count) {
            /** @var Subscription $subscription */
            foreach ($subscriptions as $subscription) {
                // Dispatch inside the tenant so the job payload carries it.
                $context->run(Tenant::query()->find($subscription->tenant_id), function () use ($subscription) {
                    RenewSubscription::dispatch($subscription->id);
                });
                $count++;
            }
        });

        $this->info("Queued {$count} renewal(s).");

        return self::SUCCESS;
    }
}
