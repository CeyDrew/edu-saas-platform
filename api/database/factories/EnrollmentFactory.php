<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Course;
use App\Domain\Learning\Models\Enrollment;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->id() ?? Tenant::factory(),
            'user_id' => fn (array $attributes) => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'course_id' => fn (array $attributes) => Course::factory()->published()->state(['tenant_id' => $attributes['tenant_id']]),
            'source' => 'purchase',
        ];
    }
}
