<?php

namespace Database\Factories;

use App\Domain\Ai\Models\AiGeneration;
use App\Domain\Catalog\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiGeneration>
 */
class AiGenerationFactory extends Factory
{
    protected $model = AiGeneration::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'tenant_id' => fn (array $a) => Lesson::acrossTenants()->whereKey($a['lesson_id'])->value('tenant_id'),
            'provider' => 'fake',
            'model' => 'fake-1',
            'status' => 'completed',
            'requested_count' => 5,
        ];
    }
}
