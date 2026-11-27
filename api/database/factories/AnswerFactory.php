<?php

namespace Database\Factories;

use App\Domain\Learning\Models\Answer;
use App\Domain\Learning\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    protected $model = Answer::class;

    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'tenant_id' => fn (array $a) => Question::acrossTenants()->whereKey($a['question_id'])->value('tenant_id'),
            'user_id' => fn (array $a) => User::factory()->state(['tenant_id' => $a['tenant_id']]),
            'selected_index' => 0,
            'is_correct' => false,
            'context' => 'quiz',
        ];
    }
}
