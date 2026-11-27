<?php

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\Section;
use App\Domain\Identity\Role;
use App\Domain\Learning\Models\Enrollment;
use App\Domain\Sales\Models\Order;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 * N+1 guard: list endpoints must run a constant number of queries no matter how many
 * rows they return. (Lazy loading also throws outside production via shouldBeStrict.)
 */

beforeEach(function () {
    $this->brand = Tenant::factory()->create(['slug' => 'kodakademi']);
    $this->headers = ['X-Tenant' => 'kodakademi'];
});

function queriesFor(callable $request): int
{
    // Warm up first so one-off work (tenant lookup cache, permission cache) is not counted.
    $request();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

function seedCourses(int $count): void
{
    asTenant(test()->brand, function () use ($count) {
        Course::factory()->published()->count($count)->create()->each(function (Course $course) {
            Lesson::factory()->count(3)->for(Section::factory()->for($course))->create();
        });
    });
}

it('lists the catalog with a constant number of queries', function () {
    seedCourses(2);
    $few = queriesFor(fn () => $this->getJson('/api/courses', $this->headers)->assertOk());

    seedCourses(8);
    $many = queriesFor(fn () => $this->getJson('/api/courses', $this->headers)->assertOk()->assertJsonCount(10, 'data'));

    expect($many)->toBe($few);
});

it('shows a course page with a constant number of queries', function () {
    seedCourses(1);
    $course = Course::query()->firstOrFail();
    $few = queriesFor(fn () => $this->getJson("/api/courses/{$course->slug}", $this->headers)->assertOk());

    asTenant($this->brand, fn () => Lesson::factory()->count(12)->for(Section::factory()->for($course))->create());
    $many = queriesFor(fn () => $this->getJson("/api/courses/{$course->slug}", $this->headers)->assertOk());

    expect($many)->toBe($few);
});

it('lists brand orders with a constant number of queries', function () {
    Sanctum::actingAs(userWithRole(Role::BrandAdmin, $this->brand));
    $makeOrders = fn (int $n) => asTenant($this->brand, fn () => Order::factory()->count($n)->create()->each(
        fn (Order $order) => $order->items()->create(['description' => 'Kurs', 'price' => 100]),
    ));

    $makeOrders(2);
    $few = queriesFor(fn () => $this->getJson('/api/brand/orders', $this->headers)->assertOk());

    $makeOrders(10);
    $many = queriesFor(fn () => $this->getJson('/api/brand/orders', $this->headers)->assertOk());

    expect($many)->toBe($few);
});

it('lists a students courses without a query per enrollment beyond progress', function () {
    $student = userWithRole(Role::Student, $this->brand);
    Sanctum::actingAs($student);
    seedCourses(6);

    asTenant($this->brand, fn () => Course::query()->take(2)->get()->each(
        fn (Course $course) => Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id]),
    ));
    $two = queriesFor(fn () => $this->getJson('/api/me/courses', $this->headers)->assertOk());

    asTenant($this->brand, fn () => Course::query()->skip(2)->take(4)->get()->each(
        fn (Course $course) => Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $course->id]),
    ));
    $six = queriesFor(fn () => $this->getJson('/api/me/courses', $this->headers)->assertOk()->assertJsonCount(6, 'data'));

    // Progress is two small indexed queries per course; everything else is constant.
    expect($six - $two)->toBe(8);
});
