<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Events\CoursePublished;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $instructor, array $data): Course
    {
        $course = new Course($data);
        $course->instructor_id = $instructor->id;
        $course->slug = $this->uniqueSlug((string) ($data['slug'] ?? $data['title']));
        $course->save();

        return $course;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Course $course, array $data): Course
    {
        if (isset($data['slug']) && $data['slug'] !== $course->slug) {
            $data['slug'] = $this->uniqueSlug((string) $data['slug'], $course->id);
        }

        $course->update($data);

        return $course;
    }

    public function publish(Course $course): Course
    {
        if (! $course->lessons()->exists()) {
            throw ValidationException::withMessages(['course' => 'Yayınlamak için en az bir ders ekleyin.']);
        }

        if ($course->isPublished()) {
            return $course;
        }

        $course->forceFill([
            'status' => CourseStatus::Published,
            'published_at' => $course->published_at ?? now(),
        ])->save();

        CoursePublished::dispatch($course);

        return $course;
    }

    public function unpublish(Course $course): Course
    {
        $course->forceFill(['status' => CourseStatus::Draft])->save();

        return $course;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addSection(Course $course, array $data): Section
    {
        return $course->sections()->create($data + [
            'position' => (int) $course->sections()->max('position') + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addLesson(Section $section, array $data): Lesson
    {
        return $section->lessons()->create($data + [
            'course_id' => $section->course_id,
            'position' => (int) $section->lessons()->max('position') + 1,
        ]);
    }

    /**
     * Rewrite positions in the given order, e.g. after drag and drop in the panel.
     *
     * @param  list<int>  $sectionIds
     */
    public function reorderSections(Course $course, array $sectionIds): void
    {
        DB::transaction(function () use ($course, $sectionIds) {
            foreach ($sectionIds as $position => $id) {
                $course->sections()->whereKey($id)->update(['position' => $position]);
            }
        });
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'kurs';
        $slug = $base;
        $i = 2;

        while (Course::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
