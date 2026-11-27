<?php

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    /** @var list<Tenant|null> */
    private array $stack = [];

    public function register(): void
    {
        $this->app->scoped(TenantContext::class);

        $this->app->singleton(TenantResolver::class, fn () => new TenantResolver(
            (string) config('tenancy.base_domain'),
        ));
    }

    public function boot(): void
    {
        $this->carryTenantThroughQueue();
    }

    /**
     * Every queued job remembers the tenant it was dispatched for and restores it
     * before handle() runs, so jobs never fall back to the unscoped platform context.
     */
    private function carryTenantThroughQueue(): void
    {
        Queue::createPayloadUsing(fn () => [
            'tenant_id' => $this->app->make(TenantContext::class)->id(),
        ]);

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $context = $this->app->make(TenantContext::class);
            $tenantId = $event->job->payload()['tenant_id'] ?? null;

            $this->stack[] = $context->get();
            $context->set($tenantId ? Tenant::query()->whereKey($tenantId)->first() : null);
        });

        $restore = function (): void {
            if ($this->stack !== []) {
                $this->app->make(TenantContext::class)->set(array_pop($this->stack));
            }
        };

        Event::listen(JobProcessed::class, $restore);
        Event::listen(JobExceptionOccurred::class, $restore);
    }
}
