<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'tenant_id' => fn (array $attributes) => Course::acrossTenants()->whereKey($attributes['course_id'])->value('tenant_id'),
            'title' => fake()->sentence(3),
            'position' => fake()->numberBetween(0, 20),
        ];
    }
}
