<?php

namespace App\Domain\Learning\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\ReviewItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $question_id
 * @property float $easiness
 * @property int $interval_days
 * @property int $repetitions
 * @property int $lapses
 * @property Carbon $due_at
 * @property Carbon|null $last_reviewed_at
 * @property-read Question $question
 */
#[Fillable(['user_id', 'question_id', 'easiness', 'interval_days', 'repetitions', 'lapses', 'due_at', 'last_reviewed_at'])]
#[UseFactory(ReviewItemFactory::class)]
class ReviewItem extends Model
{
    /** @use HasFactory<ReviewItemFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return ['easiness' => 'float', 'due_at' => 'datetime', 'last_reviewed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
