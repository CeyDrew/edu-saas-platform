<?php

namespace App\Domain\Learning\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\AnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $question_id
 * @property int $selected_index
 * @property bool $is_correct
 * @property string $context
 * @property-read Question $question
 */
#[Fillable(['user_id', 'question_id', 'quiz_attempt_id', 'selected_index', 'is_correct', 'context'])]
#[UseFactory(AnswerFactory::class)]
class Answer extends Model
{
    /** @use HasFactory<AnswerFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
