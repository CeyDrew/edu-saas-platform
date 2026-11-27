<?php

namespace App\Http\Controllers\Api\Manage;

use App\Domain\Catalog\Models\Lesson;
use App\Domain\Learning\Models\Question;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Question bank of a lesson: quiz questions and video tasks (questions pinned to a second).
 */
class QuestionController extends Controller
{
    public function index(Request $request, Lesson $lesson): JsonResponse
    {
        Gate::authorize('update', $lesson->course);

        $request->validate(['status' => ['nullable', Rule::in(['draft', 'approved', 'rejected'])]]);

        $questions = Question::query()
            ->where('lesson_id', $lesson->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByRaw('video_timestamp is null, video_timestamp')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => QuestionResource::staff($questions, $request)]);
    }

    public function store(Request $request, Lesson $lesson): JsonResponse
    {
        Gate::authorize('update', $lesson->course);

        /** @var User $user */
        $user = $request->user();
        $question = Question::query()->create($this->validated($request, $lesson) + [
            'lesson_id' => $lesson->id,
            'course_id' => $lesson->course_id,
            'source' => 'manual',
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        return response()->json(['data' => (new QuestionResource($question))->forStaff()], 201);
    }

    public function update(Request $request, Question $question): JsonResponse
    {
        Gate::authorize('update', $question->lesson->course);

        $data = $this->validated($request, $question->lesson, partial: true);
        $data += $request->validate(['status' => ['sometimes', Rule::in(['draft', 'approved', 'rejected'])]]);
        $question->update($data);

        return response()->json(['data' => (new QuestionResource($question))->forStaff()]);
    }

    public function destroy(Question $question): Response
    {
        Gate::authorize('update', $question->lesson->course);

        $question->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Lesson $lesson, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $optionCount = count((array) $request->input('options', []));

        return $request->validate([
            'stem' => [$required, 'string', 'min:5', 'max:1000'],
            'options' => [$required, 'array', 'min:2', 'max:6'],
            'options.*' => ['required', 'string', 'max:300', 'distinct'],
            'correct_index' => [$required, 'integer', 'min:0', 'max:'.max(0, ($optionCount ?: 4) - 1)],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'video_timestamp' => ['nullable', 'integer', 'min:0', 'max:'.max(0, $lesson->duration_seconds)],
        ]);
    }
}
