<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Domain\Catalog\Models\Course;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public storefront catalog of the current brand.
 */
class CourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:60'],
            'level' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'between:1,48'],
        ]);

        $perPage = $request->integer('per_page', 12);

        if ($request->filled('q')) {
            $courses = Course::search($request->string('q')->toString())
                ->query(fn ($query) => $query->published()
                    ->with(['media', 'instructor'])
                    ->withCount('lessons')
                    ->withSum('lessons', 'duration_seconds')
                    ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category'))))
                ->paginate($perPage);
        } else {
            $courses = Course::query()
                ->published()
                ->with(['media', 'instructor'])
                ->withCount('lessons')
                ->withSum('lessons', 'duration_seconds')
                ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
                ->when($request->filled('level'), fn ($query) => $query->where('level', $request->string('level')))
                ->latest('published_at')
                ->paginate($perPage);
        }

        return CourseResource::collection($courses);
    }

    public function show(Course $course): CourseResource
    {
        abort_unless($course->isPublished(), 404);

        $course->load(['media', 'instructor', 'sections.lessons.media'])
            ->loadCount('lessons')
            ->loadSum('lessons', 'duration_seconds');

        return new CourseResource($course);
    }

    /**
     * Categories with at least one published course, for storefront filters.
     */
    public function categories(): JsonResponse
    {
        $categories = Course::query()->published()->whereNotNull('category')
            ->selectRaw('category, count(*) as courses_count')
            ->groupBy('category')->orderBy('category')
            ->get()
            ->map(fn (Course $course) => ['name' => $course->category, 'courses_count' => (int) $course->getAttribute('courses_count')]);

        return response()->json(['data' => $categories]);
    }
}
