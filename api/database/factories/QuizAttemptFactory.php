<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Lesson;
use App\Domain\Learning\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'tenant_id' => fn (array $a) => Lesson::acrossTenants()->whereKey($a['lesson_id'])->value('tenant_id'),
            'user_id' => fn (array $a) => User::factory()->state(['tenant_id' => $a['tenant_id']]),
            'correct' => 3,
            'total' => 5,
        ];
    }
}
