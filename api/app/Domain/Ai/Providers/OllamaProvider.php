<?php

namespace App\Domain\Ai\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Local model through Ollama's chat API. The JSON schema is passed as "format", which
 * constrains decoding to valid JSON of that shape.
 */
class OllamaProvider implements LlmProvider
{
    public function __construct(
        private readonly string $url,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    public function name(): string
    {
        return 'ollama';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function generateJson(string $system, string $prompt, array $schema): string
    {
        try {
            $response = Http::baseUrl($this->url)->timeout($this->timeout)->post('/api/chat', [
                'model' => $this->model,
                'stream' => false,
                'format' => $schema,
                'options' => ['temperature' => 0.4],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);
        } catch (ConnectionException $e) {
            throw new LlmException("Ollama is not reachable at {$this->url}: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw new LlmException('Ollama error: '.($response->json('error') ?? $response->body()));
        }

        return (string) $response->json('message.content');
    }
}
