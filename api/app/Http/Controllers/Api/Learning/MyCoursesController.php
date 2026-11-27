<?php

namespace App\Http\Controllers\Api\Learning;

use App\Domain\Catalog\Models\Course;
use App\Domain\Learning\EnrollmentService;
use App\Domain\Learning\LearningService;
use App\Domain\Learning\Models\Enrollment;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Courses the student can study: purchased ones plus, with an active subscription,
 * every course included in the subscription.
 */
class MyCoursesController extends Controller
{
    public function __invoke(Request $request, EnrollmentService $enrollments, LearningService $learning): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $enrolled = Enrollment::query()
            ->where('user_id', $user->id)
            ->with(['course' => fn ($query) => $query->with('media')->withCount('lessons')])
            ->latest('id')
            ->get();

        $subscriptionCourses = $enrollments->activeSubscription($user) === null
            ? collect()
            : Course::query()->published()->where('included_in_subscription', true)
                ->whereNotIn('id', $enrolled->pluck('course_id'))
                ->with('media')->withCount('lessons')->get();

        $rows = $enrolled->map(fn (Enrollment $enrollment) => ['source' => $enrollment->source, 'course' => $enrollment->course])
            ->concat($subscriptionCourses->map(fn (Course $course) => ['source' => 'subscription', 'course' => $course]));

        return response()->json([
            'data' => $rows->map(fn (array $row) => [
                'source' => $row['source'],
                'course' => new CourseResource($row['course']),
                'progress' => $learning->courseProgress($user, $row['course']),
            ])->values(),
        ]);
    }
}
