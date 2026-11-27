<?php

namespace App\Domain\Learning;

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Learning\Models\Answer;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\Question;
use App\Domain\Learning\Models\QuizAttempt;
use App\Domain\Learning\Models\ReviewItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LearningService
{
    /** A lesson counts as completed once this share of the video was watched. */
    public const int COMPLETION_PERCENT = 90;

    /**
     * Save the player position. Watched time only grows by the real time between two
     * heartbeats, so seeking to the end does not complete a lesson.
     */
    public function recordProgress(User $user, Lesson $lesson, int $position, int $secondsSinceLastBeat): LessonProgress
    {
        $progress = LessonProgress::query()->firstOrNew(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['course_id' => $lesson->course_id],
        );

        $duration = max(1, $lesson->duration_seconds);
        $watched = min($duration, $progress->watched_seconds + min(max(0, $secondsSinceLastBeat), 60));
        $percent = (int) min(100, floor($watched * 100 / $duration));

        $progress->fill([
            'last_position' => min($position, $duration),
            'watched_seconds' => $watched,
            'watched_percent' => max($percent, $progress->watched_percent ?? 0),
        ]);

        if ($progress->completed_at === null && $percent >= self::COMPLETION_PERCENT) {
            $progress->completed_at = now();
        }

        $progress->save();

        return $progress;
    }

    public function complete(User $user, Lesson $lesson): LessonProgress
    {
        $progress = LessonProgress::query()->firstOrNew(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['course_id' => $lesson->course_id],
        );

        $progress->fill(['watched_percent' => 100, 'completed_at' => $progress->completed_at ?? now()])->save();

        return $progress;
    }

    /**
     * Grade one answer. Wrong answers go to the student's archive and are due for review
     * tomorrow; answers given during a review move the item along its SM-2 schedule.
     */
    public function answer(User $user, Question $question, int $selected, string $context, ?int $secondsTaken = null, ?int $attemptId = null): Answer
    {
        $correct = $selected === $question->correct_index;

        return DB::transaction(function () use ($user, $question, $selected, $context, $secondsTaken, $attemptId, $correct) {
            $answer = Answer::query()->create([
                'user_id' => $user->id,
                'question_id' => $question->id,
                'quiz_attempt_id' => $attemptId,
                'selected_index' => $selected,
                'is_correct' => $correct,
                'context' => $context,
            ]);

            $item = ReviewItem::query()->where('user_id', $user->id)->where('question_id', $question->id)->lockForUpdate()->first();

            if ($context === 'review' && $item !== null) {
                $this->schedule($item, Sm2::quality($correct, $secondsTaken));
            } elseif (! $correct) {
                $item ??= new ReviewItem(['user_id' => $user->id, 'question_id' => $question->id]);
                $this->schedule($item, Sm2::quality(false));
            }

            return $answer;
        });
    }

    /**
     * @param  array<int, int>  $answers  question id => selected option
     */
    public function submitQuiz(User $user, Lesson $lesson, array $answers): QuizAttempt
    {
        $questions = Question::query()
            ->where('lesson_id', $lesson->id)
            ->where('status', 'approved')
            ->whereNull('video_timestamp')
            ->get();

        return DB::transaction(function () use ($user, $lesson, $answers, $questions) {
            $attempt = QuizAttempt::query()->create([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'correct' => 0,
                'total' => $questions->count(),
            ]);

            $correct = 0;
            foreach ($questions as $question) {
                $selected = $answers[$question->id] ?? -1;
                $correct += (int) $this->answer($user, $question, $selected, 'quiz', attemptId: $attempt->id)->is_correct;
            }

            $attempt->forceFill(['correct' => $correct])->save();

            return $attempt;
        });
    }

    /**
     * @return array{completed_lessons: int, total_lessons: int, percent: int, next_lesson_id: int|null}
     */
    public function courseProgress(User $user, Course $course): array
    {
        $lessonIds = Lesson::query()
            ->where('lessons.course_id', $course->id)
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->orderBy('sections.position')->orderBy('lessons.position')
            ->pluck('lessons.id');

        $completed = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereNotNull('completed_at')
            ->pluck('lesson_id');

        $total = $lessonIds->count();

        return [
            'completed_lessons' => $completed->count(),
            'total_lessons' => $total,
            'percent' => $total === 0 ? 0 : (int) round($completed->count() * 100 / $total),
            'next_lesson_id' => $lessonIds->diff($completed)->first(),
        ];
    }

    private function schedule(ReviewItem $item, int $quality): void
    {
        $next = Sm2::next((float) ($item->easiness ?? 2.5), (int) ($item->interval_days ?? 0), (int) ($item->repetitions ?? 0), $quality);

        $item->forceFill([
            'easiness' => $next['easiness'],
            'interval_days' => $next['interval'],
            'repetitions' => $next['repetitions'],
            'lapses' => ($item->lapses ?? 0) + ($quality < 3 && $item->exists ? 1 : 0),
            'due_at' => now()->startOfDay()->addDays($next['interval']),
            'last_reviewed_at' => now(),
        ])->save();
    }
}
