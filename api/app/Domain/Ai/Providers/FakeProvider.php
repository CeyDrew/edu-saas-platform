<?php

namespace App\Domain\Ai\Providers;

use Illuminate\Support\Str;

/**
 * Deterministic stand-in for tests and offline demos: turns sentences of the source text
 * into "which statement appears in the lesson?" questions. Same input, same output.
 */
class FakeProvider implements LlmProvider
{
    /** @var list<string> Responses queued by tests; consumed before the generated answer. */
    private array $queued = [];

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-1';
    }

    public function queue(string ...$responses): void
    {
        array_push($this->queued, ...$responses);
    }

    public function generateJson(string $system, string $prompt, array $schema): string
    {
        if ($this->queued !== []) {
            return array_shift($this->queued);
        }

        preg_match('/COUNT=(\d+)/', $prompt, $count);
        preg_match('/<source>(.*)<\/source>/s', $prompt, $source);

        $sentences = collect(preg_split('/(?<=[.!?])\s+/u', trim($source[1] ?? '')) ?: [])
            ->map(fn (string $sentence) => trim($sentence))
            ->filter(fn (string $sentence) => mb_strlen($sentence) > 20)
            ->values();

        $distractors = ['Bu konu derste hiç işlenmedi.', 'Ders yalnızca tarihçeyi anlatır.', 'Konu ileri seviye matematik gerektirir.'];

        $questions = $sentences->take((int) ($count[1] ?? 3))->map(fn (string $sentence, int $i) => [
            'stem' => 'Derse göre aşağıdakilerden hangisi doğrudur? ('.($i + 1).'. bilgi)',
            'options' => array_merge([Str::limit($sentence, 180)], $distractors),
            'correct_index' => 0,
            'explanation' => 'Derste: '.Str::limit($sentence, 200),
            'video_second' => null,
        ])->all();

        return (string) json_encode(['questions' => $questions], JSON_UNESCAPED_UNICODE);
    }
}
