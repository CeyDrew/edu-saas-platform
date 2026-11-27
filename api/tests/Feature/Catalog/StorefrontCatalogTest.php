<?php

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\Section;
use App\Domain\Tenancy\Models\Tenant;

beforeEach(function () {
    $this->brand = Tenant::factory()->create(['slug' => 'kodakademi']);
    $this->other = Tenant::factory()->create(['slug' => 'dilokulu']);
    $this->headers = ['X-Tenant' => 'kodakademi'];
});

it('lists only published courses of the current brand', function () {
    asTenant($this->brand, function () {
        Course::factory()->published()->create(['title' => 'Yayında']);
        Course::factory()->create(['title' => 'Taslak']);
    });
    asTenant($this->other, fn () => Course::factory()->published()->create(['title' => 'Başka marka']));

    $this->getJson('/api/courses', $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Yayında');
});

it('does not show draft courses on the storefront', function () {
    $course = asTenant($this->brand, fn () => Course::factory()->create());

    $this->getJson("/api/courses/{$course->slug}", $this->headers)->assertNotFound();
});

it('shows bodies of preview lessons only', function () {
    $course = asTenant($this->brand, function () {
        $course = Course::factory()->published()->create();
        $section = Section::factory()->for($course)->create();
        Lesson::factory()->for($section)->preview()->create(['content' => 'Açık ders', 'position' => 1]);
        Lesson::factory()->for($section)->create(['content' => 'Ücretli ders', 'position' => 2]);

        return $course;
    });

    $lessons = $this->getJson("/api/courses/{$course->slug}", $this->headers)->assertOk()->json('data.sections.0.lessons');

    expect($lessons[0]['content'])->toBe('Açık ders')
        ->and($lessons[1])->not->toHaveKey('content');
});

it('searches the brand catalog', function () {
    asTenant($this->brand, function () {
        Course::factory()->published()->create(['title' => 'React ile Arayüz']);
        Course::factory()->published()->create(['title' => 'Python Temelleri']);
    });
    asTenant($this->other, fn () => Course::factory()->published()->create(['title' => 'React Native']));

    $this->getJson('/api/courses?q=React', $this->headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'React ile Arayüz');
});

it('uses a separate search index per brand', function () {
    $a = asTenant($this->brand, fn () => Course::factory()->published()->create());
    $b = asTenant($this->other, fn () => Course::factory()->published()->create());

    expect($a->searchableAs())->not->toBe($b->searchableAs())
        ->and($a->searchableAs())->toEndWith("courses_tenant{$this->brand->id}");
});

it('lists categories with course counts', function () {
    asTenant($this->brand, function () {
        Course::factory()->published()->count(2)->create(['category' => 'Yazılım']);
        Course::factory()->published()->create(['category' => 'Tasarım']);
    });

    $this->getJson('/api/categories', $this->headers)
        ->assertOk()
        ->assertJsonPath('data', [
            ['name' => 'Tasarım', 'courses_count' => 1],
            ['name' => 'Yazılım', 'courses_count' => 2],
        ]);
});
