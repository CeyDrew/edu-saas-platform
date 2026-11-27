<?php

namespace App\Http\Controllers\Api\Manage;

use App\Domain\Ai\Jobs\GenerateQuestions;
use App\Domain\Ai\Models\AiGeneration;
use App\Domain\Ai\Providers\LlmProvider;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Identity\Role;
use App\Domain\Learning\Models\Question;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * AI question generation and the human review of its drafts.
 */
class AiQuestionController extends Controller
{
    public function generate(Request $request, Lesson $lesson, LlmProvider $llm): JsonResponse
    {
        Gate::authorize('update', $lesson->course);

        $data = $request->validate(['count' => ['nullable', 'integer', 'between:1,10']]);

        abort_if(trim((string) ($lesson->transcript ?: $lesson->content)) === '', 422, 'Soru üretmek için önce ders metni veya transkript ekleyin.');

        /** @var User $user */
        $user = $request->user();
        $generation = AiGeneration::query()->create([
            'lesson_id' => $lesson->id,
            'requested_by' => $user->id,
            'provider' => $llm->name(),
            'model' => $llm->model(),
            'status' => 'queued',
            'requested_count' => $data['count'] ?? 5,
        ]);

        GenerateQuestions::dispatch($generation->id);

        return response()->json(['data' => $this->present($generation->refresh())], 202);
    }

    /**
     * Generations of the courses the user manages, newest first, with their drafts.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('viewAny', Course::class), 403);

        $request->validate(['status' => ['nullable', Rule::in(['queued', 'running', 'completed', 'failed'])]]);

        $generations = AiGeneration::query()
            ->with(['lesson.course', 'questions' => fn ($query) => $query->where('status', 'draft')])
            ->unless($user->hasRole(Role::BrandAdmin->value), fn ($query) => $query->whereHas('lesson.course', fn ($q) => $q->where('instructor_id', $user->id)))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('id')
            ->limit(30)
            ->get();

        return response()->json(['data' => $generations->map(fn (AiGeneration $g) => $this->present($g, $request))]);
    }

    public function show(Request $request, AiGeneration $generation): JsonResponse
    {
        Gate::authorize('update', $generation->lesson->course);

        return response()->json(['data' => $this->present($generation->load(['lesson.course', 'questions']), $request)]);
    }

    /**
     * Publish the chosen drafts and reject the rest of the batch.
     */
    public function review(Request $request, AiGeneration $generation): JsonResponse
    {
        Gate::authorize('update', $generation->lesson->course);

        $data = $request->validate([
            'approve' => ['present', 'array'],
            'approve.*' => ['integer', Rule::exists('questions', 'id')->where('ai_generation_id', $generation->id)],
        ]);

        $drafts = Question::query()->where('ai_generation_id', $generation->id)->where('status', 'draft');
        $approved = (clone $drafts)->whereIn('id', $data['approve'])->update(['status' => 'approved']);
        $rejected = $drafts->update(['status' => 'rejected']);

        return response()->json(['data' => ['approved' => $approved, 'rejected' => $rejected]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AiGeneration $generation, ?Request $request = null): array
    {
        return [
            'id' => $generation->id,
            'status' => $generation->status,
            'provider' => $generation->provider,
            'model' => $generation->model,
            'requested_count' => $generation->requested_count,
            'questions_created' => $generation->questions_created,
            'attempts' => $generation->attempts,
            'error' => $generation->error,
            'duration_ms' => $generation->duration_ms,
            'created_at' => $generation->created_at->toIso8601String(),
            'lesson' => $generation->relationLoaded('lesson') ? [
                'id' => $generation->lesson->id,
                'title' => $generation->lesson->title,
                'course' => $generation->lesson->relationLoaded('course') ? $generation->lesson->course->title : null,
            ] : null,
            'drafts' => $generation->relationLoaded('questions') && $request !== null
                ? QuestionResource::staff($generation->questions->where('status', 'draft'), $request)
                : [],
        ];
    }
}
