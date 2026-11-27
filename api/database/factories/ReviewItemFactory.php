<?php

namespace Database\Factories;

use App\Domain\Learning\Models\Question;
use App\Domain\Learning\Models\ReviewItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewItem>
 */
class ReviewItemFactory extends Factory
{
    protected $model = ReviewItem::class;

    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'tenant_id' => fn (array $a) => Question::acrossTenants()->whereKey($a['question_id'])->value('tenant_id'),
            'user_id' => fn (array $a) => User::factory()->state(['tenant_id' => $a['tenant_id']]),
            'easiness' => 2.5,
            'interval_days' => 1,
            'repetitions' => 0,
            'due_at' => now(),
        ];
    }
}
