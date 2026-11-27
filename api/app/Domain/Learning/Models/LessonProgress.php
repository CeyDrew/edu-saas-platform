<?php

namespace App\Domain\Learning\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\LessonProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property int $lesson_id
 * @property int $last_position
 * @property int $watched_seconds
 * @property int $watched_percent
 * @property Carbon|null $completed_at
 */
#[Fillable(['user_id', 'course_id', 'lesson_id', 'last_position', 'watched_seconds', 'watched_percent', 'completed_at'])]
#[UseFactory(LessonProgressFactory::class)]
class LessonProgress extends Model
{
    /** @use HasFactory<LessonProgressFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
