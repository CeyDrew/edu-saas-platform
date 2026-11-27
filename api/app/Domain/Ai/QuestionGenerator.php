<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Models\AiGeneration;
use App\Domain\Ai\Providers\LlmException;
use App\Domain\Ai\Providers\LlmProvider;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Learning\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lesson text in, draft multiple-choice questions out. The model's JSON is validated
 * against the schema; invalid output is retried once with the errors, never saved.
 * Questions are stored as drafts and reach students only after an instructor approves them.
 */
class QuestionGenerator
{
    public const int MAX_ATTEMPTS = 2;

    public function __construct(private readonly LlmProvider $llm) {}

    /**
     * JSON schema sent to the model (structured output) and mirrored by validate().
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'stem' => ['type' => 'string'],
                            'options' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 4, 'maxItems' => 4],
                            'correct_index' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 3],
                            'explanation' => ['type' => 'string'],
                            'video_second' => ['type' => ['integer', 'null']],
                        ],
                        'required' => ['stem', 'options', 'correct_index', 'explanation'],
                    ],
                ],
            ],
            'required' => ['questions'],
        ];
    }

    public function run(AiGeneration $generation): AiGeneration
    {
        $lesson = $generation->lesson;
        $started = hrtime(true);
        $generation->forceFill(['status' => 'running', 'provider' => $this->llm->name(), 'model' => $this->llm->model()])->save();

        $prompt = $this->prompt($lesson, $generation->requested_count);
        $error = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $raw = $this->llm->generateJson($this->system(), $prompt.($error ? "\n\nÖnceki cevabın geçersizdi: {$error}. Şemaya uygun JSON döndür." : ''), self::schema());
            } catch (LlmException $e) {
                return $this->fail($generation, $e->getMessage(), $attempt, $started);
            }

            $generation->forceFill(['raw_response' => Str::limit($raw, 60000, ''), 'attempts' => $attempt])->save();

            try {
                $questions = $this->validate($raw, $lesson);
            } catch (ValidationException $e) {
                $error = collect($e->errors())->flatten()->take(5)->implode(' ');

                continue;
            }

            $created = $this->store($generation, $lesson, $questions);

            $generation->forceFill([
                'status' => 'completed',
                'questions_created' => $created,
                'error' => null,
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            ])->save();

            return $generation;
        }

        return $this->fail($generation, "Model şemaya uygun JSON üretmedi: {$error}", self::MAX_ATTEMPTS, $started);
    }

    /**
     * @return list<array{stem: string, options: list<string>, correct_index: int, explanation: string, video_second: int|null}>
     */
    public function validate(string $raw, Lesson $lesson): array
    {
        $data = json_decode($this->stripFences($raw), true);

        if (! is_array($data)) {
            throw ValidationException::withMessages(['json' => 'Cevap geçerli bir JSON değil.']);
        }

        $validated = Validator::make($data, [
            'questions' => ['required', 'array', 'min:1', 'max:20'],
            'questions.*.stem' => ['required', 'string', 'min:10', 'max:1000'],
            'questions.*.options' => ['required', 'array', 'size:4', function (string $attribute, mixed $options, \Closure $fail) {
                // "distinct" on options.* would compare across all questions, so check per question.
                $normalized = array_map(fn ($option) => mb_strtolower(trim((string) $option)), (array) $options);
                if (count(array_unique($normalized)) !== count($normalized)) {
                    $fail("{$attribute} contains duplicate options.");
                }
            }],
            'questions.*.options.*' => ['required', 'string', 'max:300'],
            'questions.*.correct_index' => ['required', 'integer', 'between:0,3'],
            'questions.*.explanation' => ['required', 'string', 'max:2000'],
            'questions.*.video_second' => ['nullable', 'integer', 'min:0', 'max:'.max(0, $lesson->duration_seconds)],
        ])->validate();

        /** @var list<array{stem: string, options: list<string>, correct_index: int, explanation: string, video_second?: int|null}> $questions */
        $questions = $validated['questions'];

        return array_map(fn (array $q) => [
            'stem' => trim($q['stem']),
            'options' => array_map(fn ($o) => trim((string) $o), $q['options']),
            'correct_index' => (int) $q['correct_index'],
            'explanation' => trim($q['explanation']),
            'video_second' => isset($q['video_second']) ? (int) $q['video_second'] : null,
        ], $questions);
    }

    /**
     * @param  list<array{stem: string, options: list<string>, correct_index: int, explanation: string, video_second: int|null}>  $questions
     */
    private function store(AiGeneration $generation, Lesson $lesson, array $questions): int
    {
        $existing = Question::query()->where('lesson_id', $lesson->id)->pluck('stem')->map(fn ($s) => Str::lower((string) $s))->all();

        return DB::transaction(function () use ($generation, $lesson, $questions, $existing) {
            $created = 0;

            foreach ($questions as $question) {
                // Same question twice (in this batch or already in the bank) is skipped.
                if (in_array(Str::lower($question['stem']), $existing, true)) {
                    continue;
                }
                $existing[] = Str::lower($question['stem']);

                Question::query()->create([
                    'course_id' => $lesson->course_id,
                    'lesson_id' => $lesson->id,
                    'stem' => $question['stem'],
                    'options' => $question['options'],
                    'correct_index' => $question['correct_index'],
                    'explanation' => $question['explanation'],
                    'video_timestamp' => $lesson->type->value === 'video' ? $question['video_second'] : null,
                    'source' => 'ai',
                    'status' => 'draft',
                    'created_by' => $generation->requested_by,
                    'ai_generation_id' => $generation->id,
                ]);
                $created++;
            }

            return $created;
        });
    }

    private function fail(AiGeneration $generation, string $message, int $attempts, int $started): AiGeneration
    {
        $generation->forceFill([
            'status' => 'failed',
            'error' => Str::limit($message, 2000),
            'attempts' => $attempts,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ])->save();

        return $generation;
    }

    private function system(): string
    {
        return 'Sen deneyimli bir eğitmensin. Verilen ders metninden Türkçe, tek doğru cevaplı, '
            .'dört şıklı çoktan seçmeli sorular yazarsın. Sorular yalnızca metindeki bilgiye dayanır, '
            .'şıklar birbirinden farklı ve makul olur, açıklama doğru cevabın neden doğru olduğunu söyler. '
            .'Sadece istenen JSON şemasıyla cevap verirsin.';
    }

    private function prompt(Lesson $lesson, int $count): string
    {
        $source = Str::limit(trim(($lesson->transcript ?: $lesson->content) ?? ''), 12000, '');
        $video = $lesson->type->value === 'video'
            ? "Ders {$lesson->duration_seconds} saniyelik bir video. Konunun anlatıldığı yere uygun saniyeyi video_second alanına yaz."
            : 'video_second alanını null bırak.';

        return "Ders: {$lesson->title}\nCOUNT={$count}\n{$count} soru üret. {$video}\n\n<source>{$source}</source>";
    }

    private function stripFences(string $raw): string
    {
        return (string) preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($raw));
    }
}
