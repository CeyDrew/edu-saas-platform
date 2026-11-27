<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\LessonType;
use App\Domain\Catalog\Enums\VideoStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $course_id
 * @property int $section_id
 * @property string $title
 * @property LessonType $type
 * @property string|null $content
 * @property string|null $transcript
 * @property int $duration_seconds
 * @property VideoStatus $video_status
 * @property int $position
 * @property bool $is_preview
 * @property-read Course $course
 * @property-read Section $section
 */
#[Fillable(['course_id', 'section_id', 'title', 'type', 'content', 'transcript', 'duration_seconds', 'position', 'is_preview'])]
#[UseFactory(LessonFactory::class)]
class Lesson extends Model implements HasMedia
{
    /** @use HasFactory<LessonFactory> */
    use BelongsToTenant, HasFactory, InteractsWithMedia;

    protected $attributes = [
        'type' => 'video',
        'video_status' => 'none',
        'duration_seconds' => 0,
        'is_preview' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => LessonType::class,
            'video_status' => VideoStatus::class,
            'is_preview' => 'boolean',
            'duration_seconds' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('video')
            ->singleFile()
            ->acceptsMimeTypes(['video/mp4', 'video/webm', 'video/quicktime']);

        $this->addMediaCollection('thumbnail')->singleFile();

        $this->addMediaCollection('attachments')->acceptsMimeTypes(['application/pdf']);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
