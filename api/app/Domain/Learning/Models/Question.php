<?php

namespace App\Domain\Learning\Models;

use App\Domain\Catalog\Models\Lesson;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $course_id
 * @property int $lesson_id
 * @property string $stem
 * @property list<string> $options
 * @property int $correct_index
 * @property string|null $explanation
 * @property int|null $video_timestamp
 * @property string $source
 * @property string $status
 * @property int|null $ai_generation_id
 * @property-read Lesson $lesson
 */
#[Fillable(['course_id', 'lesson_id', 'stem', 'options', 'correct_index', 'explanation', 'video_timestamp', 'source', 'status', 'created_by', 'ai_generation_id'])]
#[UseFactory(QuestionFactory::class)]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return ['options' => 'array', 'correct_index' => 'integer', 'video_timestamp' => 'integer'];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
