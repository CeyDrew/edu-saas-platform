<?php

namespace Database\Factories;

use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tenant_id' => fn (array $attributes) => Order::acrossTenants()->whereKey($attributes['order_id'])->value('tenant_id'),
            'provider' => 'fake',
            'provider_payment_id' => fake()->unique()->uuid(),
            'status' => 'success',
            'amount' => 49900,
        ];
    }
}
