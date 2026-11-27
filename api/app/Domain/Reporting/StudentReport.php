<?php

namespace App\Domain\Reporting;

use App\Domain\Catalog\Models\Course;
use Illuminate\Support\Facades\DB;

/**
 * How students do in one course: progress, completion, quiz accuracy and the hardest
 * questions. Everything is aggregated in SQL; no per-student queries.
 */
class StudentReport
{
    /**
     * @return array{
     *     summary: array{students: int, average_progress: int, completion_rate: int, accuracy: int, answers: int},
     *     students: array<int, array{name: string, email: string, completed_lessons: int, progress: int, accuracy: int|null, last_activity: string|null}>,
     *     lessons: array<int, array{lesson: string, completed: int, accuracy: int|null}>,
     *     hardest_questions: array<int, array{stem: string, lesson: string, answers: int, accuracy: int}>
     * }
     */
    public function build(Course $course): array
    {
        $totalLessons = max(1, (int) DB::table('lessons')->where('course_id', $course->id)->count());

        $students = DB::table('enrollments')
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->leftJoinSub(
                DB::table('lesson_progress')->where('course_id', $course->id)->whereNotNull('completed_at')
                    ->selectRaw('user_id, count(*) completed, max(updated_at) last_activity')->groupBy('user_id'),
                'progress', 'progress.user_id', '=', 'users.id',
            )
            ->leftJoinSub(
                DB::table('answers')->join('questions', 'questions.id', '=', 'answers.question_id')
                    ->where('questions.course_id', $course->id)
                    ->selectRaw('answers.user_id, count(*) total, sum(answers.is_correct) correct')->groupBy('answers.user_id'),
                'acc', 'acc.user_id', '=', 'users.id',
            )
            ->where('enrollments.course_id', $course->id)
            ->selectRaw('users.name, users.email, coalesce(progress.completed, 0) completed, progress.last_activity, acc.total, acc.correct')
            ->orderByDesc('completed')
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'email' => (string) $row->email,
                'completed_lessons' => (int) $row->completed,
                'progress' => (int) round(min(100, $row->completed * 100 / $totalLessons)),
                'accuracy' => $row->total ? (int) round($row->correct * 100 / $row->total) : null,
                'last_activity' => $row->last_activity === null ? null : (string) $row->last_activity,
            ]);

        $answers = DB::table('answers')->join('questions', 'questions.id', '=', 'answers.question_id')
            ->where('questions.course_id', $course->id)
            ->selectRaw('count(*) total, coalesce(sum(answers.is_correct),0) correct')->first();

        $lessons = DB::table('lessons')
            ->join('sections', 'sections.id', '=', 'lessons.section_id')
            ->leftJoinSub(
                DB::table('lesson_progress')->whereNotNull('completed_at')->selectRaw('lesson_id, count(*) completed')->groupBy('lesson_id'),
                'lp', 'lp.lesson_id', '=', 'lessons.id',
            )
            ->leftJoinSub(
                DB::table('answers')->join('questions', 'questions.id', '=', 'answers.question_id')
                    ->selectRaw('questions.lesson_id, count(*) total, sum(answers.is_correct) correct')->groupBy('questions.lesson_id'),
                'qa', 'qa.lesson_id', '=', 'lessons.id',
            )
            ->where('lessons.course_id', $course->id)
            ->orderBy('sections.position')->orderBy('lessons.position')
            ->selectRaw('lessons.title, coalesce(lp.completed, 0) completed, qa.total, qa.correct')
            ->get()
            ->map(fn ($row) => [
                'lesson' => (string) $row->title,
                'completed' => (int) $row->completed,
                'accuracy' => $row->total ? (int) round($row->correct * 100 / $row->total) : null,
            ]);

        $hardest = DB::table('answers')
            ->join('questions', 'questions.id', '=', 'answers.question_id')
            ->join('lessons', 'lessons.id', '=', 'questions.lesson_id')
            ->where('questions.course_id', $course->id)
            ->selectRaw('questions.stem, lessons.title lesson, count(*) total, sum(answers.is_correct) correct')
            ->groupBy('questions.id', 'questions.stem', 'lessons.title')
            ->havingRaw('count(*) >= 3')
            ->orderByRaw('sum(answers.is_correct) / count(*) asc')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'stem' => (string) $row->stem,
                'lesson' => (string) $row->lesson,
                'answers' => (int) $row->total,
                'accuracy' => (int) round($row->correct * 100 / $row->total),
            ]);

        $count = $students->count();

        return [
            'summary' => [
                'students' => $count,
                'average_progress' => $count ? (int) round((float) $students->avg('progress')) : 0,
                'completion_rate' => $count ? (int) round($students->where('progress', 100)->count() * 100 / $count) : 0,
                'accuracy' => $answers && $answers->total ? (int) round($answers->correct * 100 / $answers->total) : 0,
                'answers' => (int) ($answers->total ?? 0),
            ],
            'students' => $students->values()->all(),
            'lessons' => $lessons->values()->all(),
            'hardest_questions' => $hardest->values()->all(),
        ];
    }
}
