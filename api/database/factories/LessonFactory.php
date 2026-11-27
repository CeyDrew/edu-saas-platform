<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\LessonType;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'course_id' => fn (array $attributes) => Section::acrossTenants()->whereKey($attributes['section_id'])->value('course_id'),
            'tenant_id' => fn (array $attributes) => Section::acrossTenants()->whereKey($attributes['section_id'])->value('tenant_id'),
            'title' => fake()->sentence(4),
            'type' => LessonType::Text,
            'content' => fake()->paragraphs(4, true),
            'duration_seconds' => fake()->numberBetween(120, 1800),
            'position' => fake()->numberBetween(0, 20),
            'is_preview' => false,
        ];
    }

    public function preview(): static
    {
        return $this->state(['is_preview' => true]);
    }
}
