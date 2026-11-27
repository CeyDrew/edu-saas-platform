<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property int $position
 * @property-read Course $course
 */
#[Fillable(['course_id', 'title', 'position'])]
#[UseFactory(SectionFactory::class)]
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }
}
