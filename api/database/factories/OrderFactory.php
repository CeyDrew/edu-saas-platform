<?php

namespace Database\Factories;

use App\Domain\Sales\Models\Order;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->id() ?? Tenant::factory(),
            'user_id' => fn (array $attributes) => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'number' => 'S'.now()->format('ymd').strtoupper(fake()->unique()->bothify('######')),
            'type' => 'course',
            'subtotal' => 49900,
            'total' => 49900,
            'payment_provider' => 'fake',
        ];
    }
}
