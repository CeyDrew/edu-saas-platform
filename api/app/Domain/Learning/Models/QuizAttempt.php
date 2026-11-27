<?php

namespace App\Domain\Learning\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\QuizAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property int $correct
 * @property int $total
 */
#[Fillable(['user_id', 'lesson_id', 'correct', 'total'])]
#[UseFactory(QuizAttemptFactory::class)]
class QuizAttempt extends Model
{
    /** @use HasFactory<QuizAttemptFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [];
    }
}
