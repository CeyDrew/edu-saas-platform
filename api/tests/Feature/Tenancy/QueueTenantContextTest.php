<?php

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

class RecordTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(TenantContext $context): void
    {
        Cache::put('seen-tenant', $context->id() ?? 'none');
    }
}

it('restores the dispatching tenant inside a queued job', function () {
    config(['queue.default' => 'database']);
    $tenant = Tenant::factory()->create();

    // A block closure, so the PendingDispatch is sent while the tenant is still active.
    app(TenantContext::class)->run($tenant, function () {
        RecordTenantJob::dispatch();
    });
    app()->forgetScopedInstances();

    $this->artisan('queue:work', ['--once' => true, '--queue' => 'default'])->assertSuccessful();

    expect(Cache::get('seen-tenant'))->toBe($tenant->id)
        ->and(app(TenantContext::class)->check())->toBeFalse();
});

it('keeps platform jobs unscoped', function () {
    config(['queue.default' => 'database']);

    RecordTenantJob::dispatch();
    $this->artisan('queue:work', ['--once' => true])->assertSuccessful();

    expect(Cache::get('seen-tenant'))->toBe('none');
});
