<?php

use App\Domain\Catalog\Enums\VideoStatus;
use App\Domain\Catalog\Events\CoursePublished;
use App\Domain\Catalog\Jobs\ProcessLessonVideo;
use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\Section;
use App\Domain\Identity\Role;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->brand = Tenant::factory()->create(['slug' => 'kodakademi']);
    $this->headers = ['X-Tenant' => 'kodakademi'];
    $this->instructor = userWithRole(Role::Instructor, $this->brand);
    Storage::fake('public');
});

it('lets an instructor build and publish a course end to end', function () {
    Event::fake([CoursePublished::class]);
    Sanctum::actingAs($this->instructor);

    $course = $this->postJson('/api/manage/courses', [
        'title' => 'Laravel ile API Geliştirme',
        'summary' => 'Sıfırdan REST API.',
        'price' => 49900,
        'level' => 'intermediate',
        'category' => 'Yazılım',
    ], $this->headers)->assertCreated()
        ->assertJsonPath('data.slug', 'laravel-ile-api-gelistirme')
        ->assertJsonPath('data.status', 'draft')
        ->json('data');

    $section = $this->postJson("/api/manage/courses/{$course['id']}/sections", ['title' => 'Giriş'], $this->headers)
        ->assertCreated()->json('data');

    $this->postJson("/api/manage/sections/{$section['id']}/lessons", [
        'title' => 'Kurulum', 'type' => 'text', 'content' => 'Docker ile kurulum.', 'is_preview' => true,
    ], $this->headers)->assertCreated()->assertJsonPath('data.position', 1);

    $this->postJson("/api/manage/courses/{$course['id']}/publish", [], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    Event::assertDispatched(CoursePublished::class);

    // Now visible on the storefront.
    $this->getJson('/api/courses', $this->headers)->assertOk()->assertJsonPath('data.0.title', 'Laravel ile API Geliştirme');
    $this->getJson('/api/courses/laravel-ile-api-gelistirme', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.sections.0.lessons.0.content', 'Docker ile kurulum.');
});

it('refuses to publish an empty course', function () {
    Sanctum::actingAs($this->instructor);
    $course = asTenant($this->brand, fn () => Course::factory()->create(['instructor_id' => $this->instructor->id]));

    $this->postJson("/api/manage/courses/{$course->id}/publish", [], $this->headers)->assertJsonValidationErrors('course');
});

it('makes slugs unique within a brand only', function () {
    $other = Tenant::factory()->create();
    asTenant($other, fn () => Course::factory()->create(['slug' => 'ingilizce-a1']));
    asTenant($this->brand, fn () => Course::factory()->create(['slug' => 'ingilizce-a1']));
    Sanctum::actingAs($this->instructor);

    $this->postJson('/api/manage/courses', ['title' => 'İngilizce A1'], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.slug', 'ingilizce-a1-2');
});

it('keeps instructors to their own courses', function () {
    $colleague = userWithRole(Role::Instructor, $this->brand);
    $course = asTenant($this->brand, fn () => Course::factory()->create(['instructor_id' => $colleague->id]));
    Sanctum::actingAs($this->instructor);

    $this->patchJson("/api/manage/courses/{$course->id}", ['title' => 'Değişti'], $this->headers)->assertForbidden();
    $this->getJson('/api/manage/courses', $this->headers)->assertOk()->assertJsonCount(0, 'data');
});

it('lets brand admins manage every course of the brand', function () {
    $course = asTenant($this->brand, fn () => Course::factory()->create(['instructor_id' => $this->instructor->id]));
    Sanctum::actingAs(userWithRole(Role::BrandAdmin, $this->brand));

    $this->patchJson("/api/manage/courses/{$course->id}", ['title' => 'Yeni Başlık'], $this->headers)
        ->assertOk()->assertJsonPath('data.title', 'Yeni Başlık');
});

it('hides other brands courses from management routes', function () {
    $other = Tenant::factory()->create();
    $foreign = asTenant($other, fn () => Course::factory()->create());
    Sanctum::actingAs(userWithRole(Role::BrandAdmin, $this->brand));

    $this->getJson("/api/manage/courses/{$foreign->id}", $this->headers)->assertNotFound();
});

it('forbids students from the management panel', function () {
    Sanctum::actingAs(userWithRole(Role::Student, $this->brand));

    $this->postJson('/api/manage/courses', ['title' => 'Kurs'], $this->headers)->assertForbidden();
});

it('uploads a cover image under the tenant directory', function () {
    Sanctum::actingAs($this->instructor);
    $course = asTenant($this->brand, fn () => Course::factory()->create(['instructor_id' => $this->instructor->id]));

    $this->post("/api/manage/courses/{$course->id}/cover", [
        'cover' => UploadedFile::fake()->image('kapak.jpg', 1200, 630),
    ], $this->headers + ['Accept' => 'application/json'])->assertOk();

    $media = $course->fresh()->getFirstMedia('cover');
    expect($media->getPathRelativeToRoot())->toStartWith("tenant{$this->brand->id}/");
});

it('queues video processing after an upload', function () {
    Queue::fake();
    Sanctum::actingAs($this->instructor);
    $lesson = asTenant($this->brand, fn () => Lesson::factory()->for(
        Section::factory()->for(Course::factory()->state(['instructor_id' => $this->instructor->id]))
    )->create(['type' => 'video']));

    $this->post("/api/manage/lessons/{$lesson->id}/video", [
        'video' => fakeVideo(),
    ], $this->headers + ['Accept' => 'application/json'])
        ->assertAccepted()
        ->assertJsonPath('data.video_status', VideoStatus::Processing->value);

    Queue::assertPushedOn('media', ProcessLessonVideo::class, fn ($job) => $job->lessonId === $lesson->id);
});

it('reorders sections', function () {
    Sanctum::actingAs($this->instructor);
    $course = asTenant($this->brand, fn () => Course::factory()->create(['instructor_id' => $this->instructor->id]));
    [$a, $b] = asTenant($this->brand, fn () => Section::factory()->count(2)->for($course)->create());

    $this->putJson("/api/manage/courses/{$course->id}/sections/order", ['sections' => [$b->id, $a->id]], $this->headers)
        ->assertNoContent();

    expect($b->fresh()->position)->toBe(0)->and($a->fresh()->position)->toBe(1);
});
