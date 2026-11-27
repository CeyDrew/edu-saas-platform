<?php

namespace App\Domain\Ai\Jobs;

use App\Domain\Ai\Models\AiGeneration;
use App\Domain\Ai\QuestionGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateQuestions implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $generationId)
    {
        $this->onQueue('ai');
    }

    public function handle(QuestionGenerator $generator): void
    {
        $generation = AiGeneration::query()->with('lesson')->findOrFail($this->generationId);

        $generator->run($generation);
    }

    public function failed(Throwable $exception): void
    {
        AiGeneration::acrossTenants()->whereKey($this->generationId)->update([
            'status' => 'failed',
            'error' => mb_substr($exception->getMessage(), 0, 2000),
        ]);
    }
}
