<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\CourseLevel;
use App\Domain\Catalog\Enums\CourseStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $instructor_id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $category
 * @property CourseLevel $level
 * @property int $price Price in kuruş.
 * @property int|null $compare_at_price
 * @property bool $included_in_subscription
 * @property CourseStatus $status
 * @property Carbon|null $published_at
 * @property-read User $instructor
 */
#[Fillable([
    'instructor_id', 'title', 'slug', 'summary', 'description', 'category', 'level',
    'price', 'compare_at_price', 'included_in_subscription',
])]
#[UseFactory(CourseFactory::class)]
class Course extends Model implements HasMedia
{
    /** @use HasFactory<CourseFactory> */
    use BelongsToTenant, HasFactory, InteractsWithMedia, Searchable;

    protected function casts(): array
    {
        return [
            'level' => CourseLevel::class,
            'status' => CourseStatus::class,
            'included_in_subscription' => 'boolean',
            'published_at' => 'datetime',
            'price' => 'integer',
            'compare_at_price' => 'integer',
        ];
    }

    protected $attributes = [
        'status' => 'draft',
        'level' => 'beginner',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('position');
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * @param  Builder<Course>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', CourseStatus::Published)->whereNotNull('published_at');
    }

    public function isPublished(): bool
    {
        return $this->status === CourseStatus::Published;
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * One search index per brand, so a query can never match another brand's catalog.
     */
    public function searchableAs(): string
    {
        $tenantId = $this->tenant_id ?? app(TenantContext::class)->id();

        return config('scout.prefix').'courses_tenant'.($tenantId ?? 0);
    }

    public function shouldBeSearchable(): bool
    {
        return $this->isPublished();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'description' => strip_tags((string) $this->description),
            'category' => $this->category,
            'level' => $this->level->value,
            'price' => $this->price,
        ];
    }
}
