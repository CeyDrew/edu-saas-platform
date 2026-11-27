<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Lesson;
use App\Domain\Learning\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'tenant_id' => fn (array $a) => Lesson::acrossTenants()->whereKey($a['lesson_id'])->value('tenant_id'),
            'course_id' => fn (array $a) => Lesson::acrossTenants()->whereKey($a['lesson_id'])->value('course_id'),
            'stem' => rtrim(fake()->sentence(8), '.').'?',
            'options' => [fake()->words(3, true), fake()->words(3, true), fake()->words(3, true), fake()->words(3, true)],
            'correct_index' => fake()->numberBetween(0, 3),
            'explanation' => fake()->sentence(),
            'video_timestamp' => null,
            'source' => 'manual',
            'status' => 'approved',
        ];
    }
}
