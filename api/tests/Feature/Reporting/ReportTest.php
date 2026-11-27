<?php

use App\Domain\Catalog\Models\Course;
use App\Domain\Catalog\Models\Lesson;
use App\Domain\Catalog\Models\Section;
use App\Domain\Identity\Role;
use App\Domain\Learning\LearningService;
use App\Domain\Learning\Models\Enrollment;
use App\Domain\Learning\Models\Question;
use App\Domain\Sales\Models\Order;
use App\Domain\Tenancy\Models\Tenant;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->brand = Tenant::factory()->create(['slug' => 'kodakademi', 'name' => 'Kod Akademi']);
    $this->other = Tenant::factory()->create(['slug' => 'dilokulu', 'name' => 'Dil Okulu']);
    $this->headers = ['X-Tenant' => 'kodakademi'];

    $paid = fn (int $total, int $daysAgo, int $fee) => Order::factory()->create([
        'total' => $total, 'subtotal' => $total,
    ])->forceFill(['status' => 'paid', 'paid_at' => now()->subDays($daysAgo), 'platform_fee' => $fee])->save();

    asTenant($this->brand, function () use ($paid) {
        $paid(50000, 1, 5000);
        $paid(30000, 1, 3000);
        $paid(20000, 40, 2000);
        Order::factory()->create(['total' => 99900]);
    });
    asTenant($this->other, fn () => $paid(70000, 2, 7000));
});

it('reports brand sales for the chosen period only', function () {
    Sanctum::actingAs(userWithRole(Role::BrandAdmin, $this->brand));

    $data = $this->getJson('/api/brand/reports/sales?days=30', $this->headers)->assertOk()->json('data');

    expect($data['totals'])->toMatchArray([
        'revenue' => 80000, 'orders' => 2, 'average_order' => 40000, 'platform_fee' => 8000, 'net' => 72000,
    ])->and($data['daily'])->toHaveCount(30)
        ->and(collect($data['daily'])->firstWhere('date', now()->subDay()->toDateString())['revenue'])->toBe(80000);

    expect($this->getJson('/api/brand/reports/sales?days=90', $this->headers)->json('data.totals.revenue'))->toBe(100000);
});

it('exports sales as an Excel-friendly CSV', function () {
    Sanctum::actingAs(userWithRole(Role::BrandAdmin, $this->brand));

    $csv = $this->get('/api/brand/reports/sales?days=7&format=csv', $this->headers)
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBFTarih;Sipariş;\"Ciro (TL)\"")
        ->and($csv)->toContain(now()->subDay()->toDateString().';2;800,00');
});

it('keeps sales reports to brand admins', function () {
    Sanctum::actingAs(userWithRole(Role::Instructor, $this->brand));

    $this->getJson('/api/brand/reports/sales', $this->headers)->assertForbidden();
});

it('shows the platform revenue and commission per brand', function () {
    Sanctum::actingAs(userWithRole(Role::PlatformAdmin));

    $this->getJson('/api/platform/reports?days=30')
        ->assertOk()
        ->assertJsonPath('data.totals.revenue', 150000)
        ->assertJsonPath('data.totals.platform_fee', 15000)
        ->assertJsonPath('data.brands.0.name', 'Dil Okulu')
        ->assertJsonPath('data.brands.1.revenue', 80000);
});

it('summarises student performance in a course', function () {
    $instructor = userWithRole(Role::Instructor, $this->brand);
    [$ayse, $ali] = [userWithRole(Role::Student, $this->brand, ['name' => 'Ayşe']), userWithRole(Role::Student, $this->brand, ['name' => 'Ali'])];

    asTenant($this->brand, function () use ($instructor, $ayse, $ali) {
        $this->course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
        $section = Section::factory()->for($this->course)->create();
        [$l1, $l2] = Lesson::factory()->count(2)->for($section)->sequence(['position' => 0], ['position' => 1])->create();
        $question = Question::factory()->for($l1)->create(['correct_index' => 0]);

        foreach ([$ayse, $ali] as $student) {
            Enrollment::factory()->create(['user_id' => $student->id, 'course_id' => $this->course->id]);
        }

        $learning = app(LearningService::class);
        $learning->complete($ayse, $l1);
        $learning->complete($ayse, $l2);
        $learning->complete($ali, $l1);
        $learning->answer($ayse, $question, 0, 'quiz');
        $learning->answer($ali, $question, 2, 'quiz');
        $learning->answer($ali, $question, 0, 'review');
    });

    Sanctum::actingAs($instructor);
    $data = $this->getJson("/api/manage/courses/{$this->course->id}/report", $this->headers)->assertOk()->json('data');

    expect($data['summary'])->toBe(['students' => 2, 'average_progress' => 75, 'completion_rate' => 50, 'accuracy' => 67, 'answers' => 3])
        ->and($data['students'][0])->toMatchArray(['name' => 'Ayşe', 'progress' => 100, 'accuracy' => 100])
        ->and($data['lessons'][0]['completed'])->toBe(2)
        ->and($data['hardest_questions'][0]['accuracy'])->toBe(67);
});
