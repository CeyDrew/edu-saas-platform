<?php

namespace Database\Factories;

use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tenant_id' => fn (array $attributes) => Order::acrossTenants()->whereKey($attributes['order_id'])->value('tenant_id'),
            'number' => 'EA'.fake()->unique()->numerify('##########'),
            'kind' => 'e-arsiv',
            'total' => 49900,
            'issued_at' => now(),
        ];
    }
}
