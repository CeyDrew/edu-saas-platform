<?php

namespace App\Http\Controllers\Api\Manage;

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Services\CourseService;
use App\Domain\Identity\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Course management for instructors and brand admins.
 */
class CourseController extends Controller
{
    public function __construct(private readonly CourseService $courses) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Course::class);

        /** @var User $user */
        $user = $request->user();

        $courses = Course::query()
            ->with(['media', 'instructor'])
            ->withCount('lessons')
            ->unless($user->hasRole(Role::BrandAdmin->value), fn ($query) => $query->where('instructor_id', $user->id))
            ->latest('id')
            ->paginate(20);

        return CourseResource::collection($courses);
    }

    public function store(CourseRequest $request): JsonResponse
    {
        Gate::authorize('create', Course::class);

        /** @var User $user */
        $user = $request->user();
        $course = $this->courses->create($user, $request->validated());

        return (new CourseResource($course))->response()->setStatusCode(201);
    }

    public function show(Request $request, Course $course): CourseResource
    {
        Gate::authorize('view', $course);

        // Staff always see lesson bodies and transcripts.
        $request->attributes->set('lesson_content', true);

        return new CourseResource($course->load(['media', 'instructor', 'sections.lessons.media'])->loadCount('lessons'));
    }

    public function update(CourseRequest $request, Course $course): CourseResource
    {
        Gate::authorize('update', $course);

        return new CourseResource($this->courses->update($course, $request->validated()));
    }

    public function destroy(Course $course): Response
    {
        Gate::authorize('delete', $course);

        $course->delete();

        return response()->noContent();
    }

    public function publish(Course $course): CourseResource
    {
        Gate::authorize('publish', $course);

        return new CourseResource($this->courses->publish($course));
    }

    public function unpublish(Course $course): CourseResource
    {
        Gate::authorize('publish', $course);

        return new CourseResource($this->courses->unpublish($course));
    }

    public function cover(Request $request, Course $course): CourseResource
    {
        Gate::authorize('update', $course);

        $request->validate(['cover' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);

        $course->addMediaFromRequest('cover')->toMediaCollection('cover');

        return new CourseResource($course->load('media'));
    }
}
