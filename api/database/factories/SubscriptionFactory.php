<?php

namespace Database\Factories;

use App\Domain\Sales\Models\Subscription;
use App\Domain\Sales\Models\SubscriptionPlan;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->id() ?? Tenant::factory(),
            'user_id' => fn (array $attributes) => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'plan_id' => fn (array $attributes) => SubscriptionPlan::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
            'card_token' => 'card_'.fake()->uuid(),
        ];
    }
}
