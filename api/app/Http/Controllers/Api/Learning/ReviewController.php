<?php

namespace App\Http\Controllers\Api\Learning;

use App\Domain\Learning\Models\Answer;
use App\Domain\Learning\Models\ReviewItem;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Personal review: today's spaced repetition list and the wrong-answer archive.
 */
class ReviewController extends Controller
{
    public function due(Request $request): JsonResponse
    {
        $items = ReviewItem::query()
            ->where('user_id', $this->user($request)->id)
            ->where('due_at', '<=', now()->endOfDay())
            ->with('question.lesson.course')
            ->orderBy('due_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $items->map(fn (ReviewItem $item) => [
                'id' => $item->id,
                'question' => new QuestionResource($item->question),
                'lesson' => $item->question->lesson->title,
                'course' => $item->question->lesson->course->title,
                'repetitions' => $item->repetitions,
            ]),
            'meta' => [
                'due_today' => $items->count(),
                'upcoming' => ReviewItem::query()->where('user_id', $this->user($request)->id)->where('due_at', '>', now()->endOfDay())->count(),
            ],
        ]);
    }

    public function mistakes(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $wrongCounts = Answer::query()
            ->where('user_id', $user->id)
            ->where('is_correct', false)
            ->selectRaw('question_id, count(*) as wrong')
            ->groupBy('question_id')
            ->pluck('wrong', 'question_id');

        $items = ReviewItem::query()
            ->where('user_id', $user->id)
            ->with('question.lesson.course')
            ->latest('updated_at')
            ->paginate(20);

        return response()->json([
            'data' => collect($items->items())->map(fn (ReviewItem $item) => [
                'id' => $item->id,
                'question' => (new QuestionResource($item->question))->forStaff(),
                'lesson' => $item->question->lesson->title,
                'course' => $item->question->lesson->course->title,
                'wrong_count' => (int) ($wrongCounts[$item->question_id] ?? 0),
                'interval_days' => $item->interval_days,
                'due_at' => $item->due_at->toIso8601String(),
                'mastered' => $item->interval_days >= 21,
            ]),
            'meta' => ['total' => $items->total(), 'last_page' => $items->lastPage()],
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
