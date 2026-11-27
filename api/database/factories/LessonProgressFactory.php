<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Lesson;
use App\Domain\Learning\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    protected $model = LessonProgress::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'tenant_id' => fn (array $a) => Lesson::acrossTenants()->whereKey($a['lesson_id'])->value('tenant_id'),
            'course_id' => fn (array $a) => Lesson::acrossTenants()->whereKey($a['lesson_id'])->value('course_id'),
            'user_id' => fn (array $a) => User::factory()->state(['tenant_id' => $a['tenant_id']]),
            'last_position' => 120,
            'watched_seconds' => 120,
            'watched_percent' => 40,
        ];
    }
}
