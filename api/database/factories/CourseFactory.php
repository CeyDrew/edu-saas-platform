<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\CourseLevel;
use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Catalog\Models\Course;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = Str::title(fake()->words(3, true)).' Eğitimi';

        return [
            'tenant_id' => fn () => app(TenantContext::class)->id() ?? Tenant::factory(),
            'instructor_id' => fn (array $attributes) => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'summary' => fake()->sentence(12),
            'description' => fake()->paragraphs(3, true),
            'category' => fake()->randomElement(['Yazılım', 'Dil', 'Sınav', 'Tasarım']),
            'level' => fake()->randomElement(CourseLevel::cases()),
            'price' => fake()->randomElement([0, 29900, 49900, 79900, 129900]),
            'compare_at_price' => null,
            'included_in_subscription' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => CourseStatus::Published, 'published_at' => now()->subDay()]);
    }

    public function paid(int $price = 49900): static
    {
        return $this->state(['price' => $price]);
    }
}
