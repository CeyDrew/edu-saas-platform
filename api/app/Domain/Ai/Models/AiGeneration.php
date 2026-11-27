<?php

namespace App\Domain\Ai\Models;

use App\Domain\Catalog\Models\Lesson;
use App\Domain\Learning\Models\Question;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\AiGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One request to generate questions for a lesson, kept for auditing what the model returned.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $lesson_id
 * @property int|null $requested_by
 * @property string $provider
 * @property string $model
 * @property string $status
 * @property int $requested_count
 * @property int $questions_created
 * @property int $attempts
 * @property string|null $raw_response
 * @property string|null $error
 * @property int|null $duration_ms
 * @property Carbon $created_at
 * @property-read Lesson $lesson
 */
#[Fillable(['lesson_id', 'requested_by', 'provider', 'model', 'status', 'requested_count'])]
#[UseFactory(AiGenerationFactory::class)]
class AiGeneration extends Model
{
    /** @use HasFactory<AiGenerationFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
