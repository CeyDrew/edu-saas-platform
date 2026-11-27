<?php

namespace Database\Factories;

use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tenant_id' => fn (array $attributes) => Order::acrossTenants()->whereKey($attributes['order_id'])->value('tenant_id'),
            'description' => fake()->sentence(3),
            'price' => 49900,
        ];
    }
}
