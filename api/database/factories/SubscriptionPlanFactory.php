<?php

namespace Database\Factories;

use App\Domain\Sales\Models\SubscriptionPlan;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->id() ?? Tenant::factory(),
            'name' => 'Aylık Tüm Erişim',
            'description' => 'Abonelik kapsamındaki tüm kurslara erişim.',
            'price' => 19900,
            'interval_months' => 1,
            'is_active' => true,
        ];
    }
}
