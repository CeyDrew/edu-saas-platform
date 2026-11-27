<?php

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\Section;
use App\Domain\Identity\Role;
use App\Domain\Learning\Models\Enrollment;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\Question;
use App\Domain\Learning\Models\ReviewItem;
use App\Domain\Tenancy\Models\Tenant;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->brand = Tenant::factory()->create(['slug' => 'kodakademi']);
    $this->headers = ['X-Tenant' => 'kodakademi'];
    $this->student = userWithRole(Role::Student, $this->brand);

    asTenant($this->brand, function () {
        $this->course = Course::factory()->published()->paid()->create(['slug' => 'php', 'included_in_subscription' => false]);
        $section = Section::factory()->for($this->course)->create(['position' => 0]);
        $this->lesson = Lesson::factory()->for($section)->create(['type' => 'video', 'duration_seconds' => 600, 'position' => 0]);
        $this->second = Lesson::factory()->for($section)->create(['type' => 'text', 'position' => 1]);

        $this->videoTask = Question::factory()->for($this->lesson)->create(['video_timestamp' => 120, 'correct_index' => 2]);
        $this->quiz = Question::factory()->count(2)->for($this->lesson)->create(['correct_index' => 1]);
        Question::factory()->for($this->lesson)->create(['status' => 'draft']);
    });

    Sanctum::actingAs($this->student);
});

function enroll(): void
{
    asTenant(test()->brand, fn () => Enrollment::factory()->create(['user_id' => test()->student->id, 'course_id' => test()->course->id]));
}

it('locks lessons of courses the student has not bought', function () {
    $this->getJson("/api/learn/lessons/{$this->lesson->id}", $this->headers)->assertForbidden();
    $this->getJson('/api/learn/courses/php', $this->headers)->assertForbidden();
});

it('serves the lesson with video tasks and quiz but without answers', function () {
    enroll();

    $data = $this->getJson("/api/learn/lessons/{$this->lesson->id}", $this->headers)->assertOk()->json('data');

    expect($data['video_tasks'])->toHaveCount(1)
        ->and($data['video_tasks'][0]['video_timestamp'])->toBe(120)
        ->and($data['video_tasks'][0])->not->toHaveKey('correct_index')
        ->and($data['quiz'])->toHaveCount(2)
        ->and($data['lesson']['content'])->not->toBeNull();
});

it('tracks watched time from heartbeats and completes at 90 percent', function () {
    enroll();
    $url = "/api/learn/lessons/{$this->lesson->id}/progress";

    // Seeking to the end does not count as watching.
    $this->postJson($url, ['position' => 590, 'elapsed' => 5], $this->headers)->assertJsonPath('data.completed', false);

    foreach (range(1, 9) as $beat) {
        $this->postJson($url, ['position' => $beat * 60, 'elapsed' => 60], $this->headers);
    }

    $this->postJson($url, ['position' => 600, 'elapsed' => 60], $this->headers)
        ->assertJsonPath('data.completed', true)
        ->assertJsonPath('data.watched_percent', 100);

    $this->getJson('/api/learn/courses/php', $this->headers)
        ->assertJsonPath('data.progress.completed_lessons', 1)
        ->assertJsonPath('data.progress.percent', 50)
        ->assertJsonPath('data.progress.next_lesson_id', $this->second->id);
});

it('files wrong answers into the archive and schedules them for tomorrow', function () {
    enroll();

    $this->postJson("/api/learn/questions/{$this->videoTask->id}/answer", ['selected_index' => 0, 'context' => 'video'], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.correct', false)
        ->assertJsonPath('data.correct_index', 2);

    $item = ReviewItem::query()->where('user_id', $this->student->id)->firstOrFail();
    expect($item->due_at->isSameDay(now()->addDay()))->toBeTrue()->and($item->interval_days)->toBe(1);

    $this->getJson('/api/learn/mistakes', $this->headers)->assertJsonPath('data.0.wrong_count', 1);
});

it('moves review items along the SM-2 schedule', function () {
    enroll();
    $this->postJson("/api/learn/questions/{$this->videoTask->id}/answer", ['selected_index' => 0, 'context' => 'video'], $this->headers);

    $this->getJson('/api/learn/review', $this->headers)->assertJsonPath('meta.due_today', 0);

    $this->travel(1)->days();
    $this->getJson('/api/learn/review', $this->headers)->assertJsonPath('meta.due_today', 1);

    $this->postJson("/api/learn/questions/{$this->videoTask->id}/answer", ['selected_index' => 2, 'context' => 'review'], $this->headers)
        ->assertJsonPath('data.correct', true);

    $item = ReviewItem::query()->firstOrFail();
    expect($item->repetitions)->toBe(1)->and($item->due_at->isSameDay(now()->addDay()))->toBeTrue();

    $this->travel(1)->days();
    $this->postJson("/api/learn/questions/{$this->videoTask->id}/answer", ['selected_index' => 2, 'context' => 'review'], $this->headers);

    expect($item->fresh()->interval_days)->toBe(6);
    $this->getJson('/api/learn/review', $this->headers)->assertJsonPath('meta.due_today', 0)->assertJsonPath('meta.upcoming', 1);
});

it('grades a lesson quiz', function () {
    enroll();
    [$first, $second] = $this->quiz;

    $this->postJson("/api/learn/lessons/{$this->lesson->id}/quiz", ['answers' => [$first->id => 1, $second->id => 3]], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.correct', 1)
        ->assertJsonPath('data.total', 2);

    expect(ReviewItem::query()->pluck('question_id')->all())->toBe([$second->id]);
});

it('does not reveal draft questions', function () {
    enroll();
    $draft = Question::query()->where('status', 'draft')->firstOrFail();

    $this->postJson("/api/learn/questions/{$draft->id}/answer", ['selected_index' => 0, 'context' => 'quiz'], $this->headers)->assertNotFound();
});

it('lists my courses with progress', function () {
    enroll();
    asTenant($this->brand, fn () => LessonProgress::factory()->create([
        'user_id' => $this->student->id, 'lesson_id' => $this->lesson->id, 'completed_at' => now(),
    ]));

    $this->getJson('/api/me/courses', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.0.progress.percent', 50);
});

it('lets instructors manage the question bank', function () {
    Sanctum::actingAs(userWithRole(Role::Instructor, $this->brand));
    $this->course->forceFill(['instructor_id' => auth()->id()])->save();

    $this->postJson("/api/manage/lessons/{$this->lesson->id}/questions", [
        'stem' => 'Laravel hangi dilde yazılmıştır?',
        'options' => ['PHP', 'Python', 'Go', 'Ruby'],
        'correct_index' => 0,
        'video_timestamp' => 300,
    ], $this->headers)->assertCreated()->assertJsonPath('data.correct_index', 0);

    $this->postJson("/api/manage/lessons/{$this->lesson->id}/questions", [
        'stem' => 'Geçersiz zaman', 'options' => ['a', 'b'], 'correct_index' => 0, 'video_timestamp' => 9999,
    ], $this->headers)->assertJsonValidationErrors('video_timestamp');

    $this->getJson("/api/manage/lessons/{$this->lesson->id}/questions", $this->headers)->assertJsonCount(5, 'data');
});
