<?php

namespace App\Http\Controllers\Api\Manage;

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Section;
use App\Domain\Catalog\Services\CourseService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SectionRequest;
use App\Http\Resources\SectionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    public function __construct(private readonly CourseService $courses) {}

    public function store(SectionRequest $request, Course $course): JsonResponse
    {
        Gate::authorize('update', $course);

        $section = $this->courses->addSection($course, $request->validated());

        return (new SectionResource($section))->response()->setStatusCode(201);
    }

    public function update(SectionRequest $request, Section $section): SectionResource
    {
        Gate::authorize('update', $section->course);

        $section->update($request->validated());

        return new SectionResource($section);
    }

    public function destroy(Section $section): Response
    {
        Gate::authorize('update', $section->course);

        $section->delete();

        return response()->noContent();
    }

    public function reorder(Request $request, Course $course): Response
    {
        Gate::authorize('update', $course);

        $data = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*' => ['integer', Rule::exists('sections', 'id')->where('course_id', $course->id)],
        ]);

        $this->courses->reorderSections($course, array_values($data['sections']));

        return response()->noContent();
    }
}
