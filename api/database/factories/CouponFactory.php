<?php

namespace Database\Factories;

use App\Domain\Sales\Models\Coupon;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->id() ?? Tenant::factory(),
            'code' => strtoupper(fake()->unique()->bothify('KUPON##??')),
            'type' => 'percent',
            'value' => 20,
            'max_uses' => null,
            'min_amount' => 0,
            'expires_at' => null,
            'is_active' => true,
        ];
    }
}
