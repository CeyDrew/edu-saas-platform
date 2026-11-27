<?php

use App\Domain\Ai\Models\AiGeneration;
use App\Domain\Ai\Providers\FakeProvider;
use App\Domain\Ai\Providers\GeminiProvider;
use App\Domain\Ai\Providers\LlmProvider;
use App\Domain\Ai\Providers\OllamaProvider;
use App\Domain\Ai\QuestionGenerator;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\Section;
use App\Domain\Identity\Role;
use App\Domain\Learning\Models\Enrollment;
use App\Domain\Learning\Models\Question;
use App\Domain\Learning\Models\ReviewItem;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

const LESSON_TEXT = 'Eloquent, Laravel ile gelen ORM katmanıdır. Her tablo bir model sınıfı ile temsil edilir. '
    .'İlişkiler hasMany ve belongsTo gibi metotlarla tanımlanır. N+1 problemini önlemek için with() ile eager loading yapılır.';

beforeEach(function () {
    config(['ai.driver' => 'fake']);
    $this->brand = Tenant::factory()->create(['slug' => 'kodakademi']);
    $this->headers = ['X-Tenant' => 'kodakademi'];
    $this->instructor = userWithRole(Role::Instructor, $this->brand);

    $this->lesson = asTenant($this->brand, function () {
        $course = Course::factory()->published()->create(['instructor_id' => $this->instructor->id, 'slug' => 'laravel']);

        return Lesson::factory()->for(Section::factory()->for($course))->create([
            'type' => 'video', 'duration_seconds' => 900, 'transcript' => LESSON_TEXT,
        ]);
    });

    Sanctum::actingAs($this->instructor);
});

it('generates draft questions deterministically with the fake provider', function () {
    $response = $this->postJson("/api/manage/lessons/{$this->lesson->id}/ai-questions", ['count' => 3], $this->headers)
        ->assertAccepted();

    $generation = AiGeneration::query()->findOrFail($response->json('data.id'));
    expect($generation->error)->toBeNull();
    expect($generation->status)->toBe('completed')
        ->and($generation->questions_created)->toBe(3)
        ->and($generation->provider)->toBe('fake');

    $questions = Question::query()->where('ai_generation_id', $generation->id)->get();
    expect($questions)->toHaveCount(3)
        ->and($questions->pluck('status')->unique()->all())->toBe(['draft'])
        ->and($questions->pluck('source')->unique()->all())->toBe(['ai'])
        ->and($questions->first()->options[0])->toBe('Eloquent, Laravel ile gelen ORM katmanıdır.');
});

it('keeps drafts away from students until an instructor approves them', function () {
    $student = userWithRole(Role::Student, $this->brand);
    asTenant($this->brand, fn () => Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $this->lesson->course_id]));

    $generationId = $this->postJson("/api/manage/lessons/{$this->lesson->id}/ai-questions", ['count' => 3], $this->headers)->json('data.id');
    $drafts = Question::query()->where('ai_generation_id', $generationId)->pluck('id');

    Sanctum::actingAs($student);
    $this->getJson("/api/learn/lessons/{$this->lesson->id}", $this->headers)->assertJsonCount(0, 'data.quiz');

    Sanctum::actingAs($this->instructor);
    $this->getJson('/api/manage/ai-generations', $this->headers)
        ->assertJsonCount(3, 'data.0.drafts')
        ->assertJsonPath('data.0.drafts.0.correct_index', 0)
        ->assertJsonPath('data.0.drafts.0.status', 'draft');
    $this->postJson("/api/manage/ai-generations/{$generationId}/review", ['approve' => [$drafts[0], $drafts[1]]], $this->headers)
        ->assertJsonPath('data.approved', 2)
        ->assertJsonPath('data.rejected', 1);

    Sanctum::actingAs($student);
    $visible = $this->getJson("/api/learn/lessons/{$this->lesson->id}", $this->headers)->json('data');
    expect(count($visible['quiz']) + count($visible['video_tasks']))->toBe(2);
});

it('retries once with the validation errors and then fails without saving anything', function () {
    $fake = app(FakeProvider::class);
    $fake->queue('bu json değil', json_encode(['questions' => [['stem' => 'Kısa?', 'options' => ['a', 'a'], 'correct_index' => 7, 'explanation' => 'x']]]));

    $generationId = $this->postJson("/api/manage/lessons/{$this->lesson->id}/ai-questions", ['count' => 2], $this->headers)->json('data.id');

    $generation = AiGeneration::query()->findOrFail($generationId);
    expect($generation->status)->toBe('failed')
        ->and($generation->attempts)->toBe(2)
        ->and($generation->error)->toContain('şemaya uygun')
        ->and(Question::query()->count())->toBe(0);
});

it('accepts a valid answer after one invalid attempt', function () {
    app(FakeProvider::class)->queue('{"questions": []}');

    $generationId = $this->postJson("/api/manage/lessons/{$this->lesson->id}/ai-questions", ['count' => 2], $this->headers)->json('data.id');

    expect(AiGeneration::query()->find($generationId))
        ->status->toBe('completed')
        ->attempts->toBe(2)
        ->questions_created->toBe(2);
});

it('validates the schema strictly', function (array $question, string $field) {
    $generator = new QuestionGenerator(app(FakeProvider::class));
    $valid = ['stem' => 'Eloquent nedir ve ne işe yarar?', 'options' => ['ORM', 'Kuyruk', 'Önbellek', 'Şablon'], 'correct_index' => 0, 'explanation' => 'ORM katmanı.', 'video_second' => 30];

    expect(fn () => $generator->validate(json_encode(['questions' => [array_merge($valid, $question)]]), $this->lesson))
        ->toThrow(ValidationException::class, $field);
})->with([
    'three options' => [['options' => ['a', 'b', 'c']], 'options'],
    'duplicate options' => [['options' => ['ORM', 'orm', 'x', 'y']], 'duplicate options'],
    'answer out of range' => [['correct_index' => 4], 'correct_index'],
    'second after the video' => [['video_second' => 5000], 'video_second'],
    'missing explanation' => [['explanation' => null], 'explanation'],
]);

it('accepts JSON wrapped in markdown fences', function () {
    $generator = new QuestionGenerator(app(FakeProvider::class));
    $json = json_encode(['questions' => [['stem' => 'Eloquent nedir ve ne işe yarar?', 'options' => ['ORM', 'Kuyruk', 'Önbellek', 'Şablon'], 'correct_index' => 0, 'explanation' => 'ORM.']]]);

    expect($generator->validate("```json\n{$json}\n```", $this->lesson))->toHaveCount(1);
});

it('refuses lessons without text', function () {
    $this->lesson->forceFill(['transcript' => null, 'content' => null])->save();

    $this->postJson("/api/manage/lessons/{$this->lesson->id}/ai-questions", [], $this->headers)->assertStatus(422);
});

it('does not let instructors generate for other instructors courses', function () {
    Sanctum::actingAs(userWithRole(Role::Instructor, $this->brand));

    $this->postJson("/api/manage/lessons/{$this->lesson->id}/ai-questions", [], $this->headers)->assertForbidden();
});

it('talks to Ollama with the JSON schema as format', function () {
    Http::fake(['ollama:11434/api/chat' => Http::response(['message' => ['content' => '{"questions":[]}']])]);

    $raw = (new OllamaProvider('http://ollama:11434', 'llama3.2:3b', 30))->generateJson('sys', 'prompt', QuestionGenerator::schema());

    expect($raw)->toBe('{"questions":[]}');
    Http::assertSent(fn (HttpRequest $r) => $r['model'] === 'llama3.2:3b' && $r['stream'] === false
        && $r['format']['required'] === ['questions'] && $r['messages'][0]['role'] === 'system');
});

it('talks to Gemini in JSON mode', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"questions":[]}']]]]]])]);

    (new GeminiProvider('test-key', 'gemini-2.5-flash', 'https://generativelanguage.googleapis.com/v1beta'))
        ->generateJson('sys', 'prompt', QuestionGenerator::schema());

    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/models/gemini-2.5-flash:generateContent')
        && $r->header('x-goog-api-key')[0] === 'test-key'
        && $r['generationConfig']['responseMimeType'] === 'application/json');
});

it('picks Ollama by default and Gemini when a key is present', function () {
    config(['ai.driver' => 'ollama', 'ai.gemini.key' => '']);
    expect(app(LlmProvider::class))->toBeInstanceOf(OllamaProvider::class);

    config(['ai.gemini.key' => 'abc']);
    expect(app(LlmProvider::class))->toBeInstanceOf(GeminiProvider::class);
});

it('records a failure when the model server is down', function () {
    config(['ai.driver' => 'ollama', 'ai.gemini.key' => '', 'ai.ollama.url' => 'http://127.0.0.1:1']);

    $generationId = $this->postJson("/api/manage/lessons/{$this->lesson->id}/ai-questions", [], $this->headers)->json('data.id');

    expect(AiGeneration::query()->find($generationId))->status->toBe('failed')->error->toContain('not reachable');
});

it('gives students review advice from their weak lessons', function () {
    $student = userWithRole(Role::Student, $this->brand);
    $question = asTenant($this->brand, fn () => Question::factory()->for($this->lesson)->create());
    asTenant($this->brand, fn () => ReviewItem::factory()->create(['user_id' => $student->id, 'question_id' => $question->id, 'lapses' => 2]));
    app(FakeProvider::class)->queue(json_encode(['summary' => 'Eloquent ilişkilerini tekrar et.', 'focus' => [['lesson' => $this->lesson->title, 'reason' => '2 kez unuttun']]]));

    Sanctum::actingAs($student);
    $this->getJson('/api/learn/recommendation', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.summary', 'Eloquent ilişkilerini tekrar et.')
        ->assertJsonPath('data.generated_by', 'fake');
});
