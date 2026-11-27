<?php

namespace App\Http\Controllers\Api\Learning;

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Learning\EnrollmentService;
use App\Domain\Learning\LearningService;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\Question;
use App\Domain\Learning\Models\QuizAttempt;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\LessonResource;
use App\Http\Resources\QuestionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The study area: lessons, video tasks, quizzes and progress of courses the student can access.
 */
class LearnController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly LearningService $learning,
    ) {}

    public function course(Request $request, Course $course): JsonResponse
    {
        $this->ensureAccess($request, $course);
        $request->attributes->set('lesson_content', true);

        $course->load(['sections.lessons.media', 'instructor', 'media'])->loadCount('lessons');
        $progress = LessonProgress::query()
            ->where('user_id', $this->user($request)->id)
            ->where('course_id', $course->id)
            ->get(['lesson_id', 'watched_percent', 'last_position', 'completed_at'])
            ->keyBy('lesson_id');

        return response()->json([
            'data' => [
                'course' => new CourseResource($course),
                'progress' => $this->learning->courseProgress($this->user($request), $course),
                'lessons' => $progress->map(fn (LessonProgress $row) => [
                    'watched_percent' => $row->watched_percent,
                    'last_position' => $row->last_position,
                    'completed' => $row->completed_at !== null,
                ]),
            ],
        ]);
    }

    public function lesson(Request $request, Lesson $lesson): JsonResponse
    {
        $this->ensureAccess($request, $lesson->course);
        $request->attributes->set('lesson_content', true);

        $questions = Question::query()->where('lesson_id', $lesson->id)->where('status', 'approved')->orderBy('id')->get();
        $user = $this->user($request);

        return response()->json([
            'data' => [
                'lesson' => new LessonResource($lesson->load('media')),
                'video_tasks' => QuestionResource::collection($questions->whereNotNull('video_timestamp')->sortBy('video_timestamp')->values()),
                'quiz' => QuestionResource::collection($questions->whereNull('video_timestamp')->values()),
                'progress' => LessonProgress::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)
                    ->first(['watched_percent', 'last_position', 'completed_at']),
                'last_attempt' => QuizAttempt::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)
                    ->latest('id')->first(['correct', 'total', 'created_at']),
            ],
        ]);
    }

    public function progress(Request $request, Lesson $lesson): JsonResponse
    {
        $this->ensureAccess($request, $lesson->course);
        $data = $request->validate([
            'position' => ['required', 'integer', 'min:0'],
            'elapsed' => ['required', 'integer', 'min:0', 'max:600'],
        ]);

        $progress = $this->learning->recordProgress($this->user($request), $lesson, $data['position'], $data['elapsed']);

        return response()->json(['data' => [
            'watched_percent' => $progress->watched_percent,
            'completed' => $progress->completed_at !== null,
        ]]);
    }

    public function complete(Request $request, Lesson $lesson): JsonResponse
    {
        $this->ensureAccess($request, $lesson->course);
        $this->learning->complete($this->user($request), $lesson);

        return response()->json(['data' => $this->learning->courseProgress($this->user($request), $lesson->course)]);
    }

    public function answer(Request $request, Question $question): JsonResponse
    {
        abort_unless($question->isApproved(), 404);
        $this->ensureAccess($request, $question->lesson->course);

        $data = $request->validate([
            'selected_index' => ['required', 'integer', 'between:0,9'],
            'context' => ['required', Rule::in(['video', 'quiz', 'review'])],
            'seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $answer = $this->learning->answer($this->user($request), $question, $data['selected_index'], $data['context'], $data['seconds'] ?? null);

        return response()->json(['data' => [
            'correct' => $answer->is_correct,
            'correct_index' => $question->correct_index,
            'explanation' => $question->explanation,
        ]]);
    }

    public function quiz(Request $request, Lesson $lesson): JsonResponse
    {
        $this->ensureAccess($request, $lesson->course);
        $data = $request->validate(['answers' => ['present', 'array'], 'answers.*' => ['integer', 'between:0,9']]);

        $attempt = $this->learning->submitQuiz($this->user($request), $lesson, array_map('intval', $data['answers']));
        $questions = Question::query()->where('lesson_id', $lesson->id)->where('status', 'approved')->whereNull('video_timestamp')->get();

        return response()->json(['data' => [
            'correct' => $attempt->correct,
            'total' => $attempt->total,
            'results' => $questions->map(fn (Question $question) => [
                'question_id' => $question->id,
                'selected_index' => $data['answers'][$question->id] ?? null,
                'correct_index' => $question->correct_index,
                'explanation' => $question->explanation,
            ])->values(),
        ]]);
    }

    private function ensureAccess(Request $request, Course $course): void
    {
        abort_unless($course->isPublished() || $this->user($request)->can('update', $course), 404);
        abort_unless($this->enrollments->canAccess($this->user($request), $course), 403, 'Bu kursa erişimin yok.');
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
