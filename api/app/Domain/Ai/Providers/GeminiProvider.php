<?php

namespace App\Domain\Ai\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini generateContent with a response schema (JSON mode).
 */
class GeminiProvider implements LlmProvider
{
    public function __construct(
        private readonly string $key,
        private readonly string $model,
        private readonly string $url,
    ) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function generateJson(string $system, string $prompt, array $schema): string
    {
        try {
            $response = Http::baseUrl($this->url)
                ->withHeaders(['x-goog-api-key' => $this->key])
                ->timeout(90)
                ->retry(2, 1000, throw: false)
                ->post("/models/{$this->model}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'responseMimeType' => 'application/json',
                        'responseJsonSchema' => $schema,
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new LlmException('Gemini is not reachable: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new LlmException('Gemini error: '.($response->json('error.message') ?? $response->body()));
        }

        return (string) $response->json('candidates.0.content.parts.0.text');
    }
}
