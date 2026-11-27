<?php

namespace App\Http\Controllers\Api\Manage;

use App\Domain\Catalog\Enums\VideoStatus;
use App\Domain\Catalog\Jobs\ProcessLessonVideo;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\Section;
use App\Domain\Catalog\Services\CourseService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\LessonRequest;
use App\Http\Resources\LessonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LessonController extends Controller
{
    public function __construct(private readonly CourseService $courses) {}

    public function store(LessonRequest $request, Section $section): JsonResponse
    {
        Gate::authorize('update', $section->course);

        $lesson = $this->courses->addLesson($section, $request->validated());

        return (new LessonResource($lesson))->withContent()->response()->setStatusCode(201);
    }

    public function update(LessonRequest $request, Lesson $lesson): LessonResource
    {
        Gate::authorize('update', $lesson->course);

        $lesson->update($request->validated());

        return (new LessonResource($lesson))->withContent();
    }

    public function destroy(Lesson $lesson): Response
    {
        Gate::authorize('update', $lesson->course);

        $lesson->delete();

        return response()->noContent();
    }

    /**
     * Upload the lesson video; duration and thumbnail are filled in by a queued job.
     */
    public function video(Request $request, Lesson $lesson): JsonResponse
    {
        Gate::authorize('update', $lesson->course);

        $request->validate(['video' => ['required', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:204800']]);

        $lesson->addMediaFromRequest('video')->toMediaCollection('video');
        $lesson->forceFill(['video_status' => VideoStatus::Processing])->save();

        ProcessLessonVideo::dispatch($lesson->id);

        return (new LessonResource($lesson->load('media')))->withContent()->response()->setStatusCode(202);
    }
}
