<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Providers\LlmException;
use App\Domain\Ai\Providers\LlmProvider;
use App\Domain\Learning\Models\ReviewItem;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Personal study advice from the student's wrong-answer archive. The model only sees
 * lesson titles and counts, never personal data. Without a model a rule-based text is used.
 */
class ReviewAdvisor
{
    public function __construct(
        private readonly LlmProvider $llm,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @return array{summary: string, focus: list<array{lesson: string, reason: string}>, generated_by: string}
     */
    public function advise(User $user): array
    {
        $weak = ReviewItem::query()
            ->where('review_items.user_id', $user->id)
            ->join('questions', 'questions.id', '=', 'review_items.question_id')
            ->join('lessons', 'lessons.id', '=', 'questions.lesson_id')
            ->where('review_items.interval_days', '<', 21)
            ->selectRaw('lessons.title as lesson, count(*) as open_items, sum(review_items.lapses) as lapses')
            ->groupBy('lessons.title')
            ->orderByDesc('open_items')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['lesson' => (string) $row->getAttribute('lesson'), 'open' => (int) $row->getAttribute('open_items'), 'lapses' => (int) $row->getAttribute('lapses')])
            ->values()
            ->all();

        if ($weak === []) {
            return ['summary' => 'Tekrar listen temiz. Yeni derslere geçebilir, haftada bir eski konulara göz atabilirsin.', 'focus' => [], 'generated_by' => 'rules'];
        }

        $key = $this->tenants->prefix("review-advice:{$user->id}:".md5((string) json_encode($weak)));

        return Cache::remember($key, now()->addHours(12), fn () => $this->fromModel($weak) ?? $this->fromRules($weak));
    }

    /**
     * @param  array<int, array{lesson: string, open: int, lapses: int}>  $weak
     * @return array{summary: string, focus: list<array{lesson: string, reason: string}>, generated_by: string}|null
     */
    private function fromModel(array $weak): ?array
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'focus' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'lesson' => ['type' => 'string'], 'reason' => ['type' => 'string'],
                ], 'required' => ['lesson', 'reason']]],
            ],
            'required' => ['summary', 'focus'],
        ];

        $lines = collect($weak)->map(fn ($w) => "- {$w['lesson']}: {$w['open']} açık soru, {$w['lapses']} kez tekrar unutuldu")->implode("\n");

        try {
            $raw = $this->llm->generateJson(
                'Sen destekleyici bir öğrenme koçusun. Kısa, somut ve Türkçe öneriler verirsin.',
                "Öğrencinin zorlandığı dersler:\n{$lines}\n\n2-3 cümlelik bir özet ve en fazla 3 odak dersi için kısa gerekçe yaz.",
                $schema,
            );

            /** @var array{summary: string, focus: list<array{lesson: string, reason: string}>} $data */
            $data = Validator::make((array) json_decode($raw, true), [
                'summary' => ['required', 'string', 'max:1000'],
                'focus' => ['present', 'array', 'max:5'],
                'focus.*.lesson' => ['required', 'string', 'max:200'],
                'focus.*.reason' => ['required', 'string', 'max:500'],
            ])->validate();

            return ['summary' => $data['summary'], 'focus' => $data['focus'], 'generated_by' => $this->llm->name()];
        } catch (LlmException|ValidationException) {
            return null;
        }
    }

    /**
     * @param  array<int, array{lesson: string, open: int, lapses: int}>  $weak
     * @return array{summary: string, focus: list<array{lesson: string, reason: string}>, generated_by: string}
     */
    private function fromRules(array $weak): array
    {
        $top = $weak[0];

        return [
            'summary' => "En çok zorlandığın konu “{$top['lesson']}”. Önce bu dersin notunu tekrar oku, ardından günlük tekrar listeni bitir.",
            'focus' => array_map(fn ($w) => [
                'lesson' => $w['lesson'],
                'reason' => "{$w['open']} soru hâlâ tekrar listende".($w['lapses'] > 0 ? ", {$w['lapses']} kez tekrar unutuldu." : '.'),
            ], array_slice($weak, 0, 3)),
            'generated_by' => 'rules',
        ];
    }
}
