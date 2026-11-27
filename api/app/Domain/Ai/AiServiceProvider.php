<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Models\AiGeneration;
use App\Domain\Ai\Providers\FakeProvider;
use App\Domain\Ai\Providers\GeminiProvider;
use App\Domain\Ai\Providers\LlmProvider;
use App\Domain\Ai\Providers\OllamaProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FakeProvider::class);

        $this->app->bind(LlmProvider::class, function ($app) {
            $driver = (string) config('ai.driver');
            $geminiKey = (string) config('ai.gemini.key');

            // Local Ollama by default; a Gemini key switches to Gemini unless forced to fake.
            if ($driver === 'fake') {
                return $app->make(FakeProvider::class);
            }

            if ($driver === 'gemini' || $geminiKey !== '') {
                return new GeminiProvider($geminiKey, (string) config('ai.gemini.model'), (string) config('ai.gemini.url'));
            }

            return new OllamaProvider((string) config('ai.ollama.url'), (string) config('ai.ollama.model'), (int) config('ai.ollama.timeout'));
        });
    }

    public function boot(): void
    {
        Route::model('generation', AiGeneration::class);
    }
}
