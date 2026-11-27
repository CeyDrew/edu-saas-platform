<?php

namespace App\Domain\Learning\Models;

use App\Domain\Catalog\Models\Course;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property int $course_id
 * @property int|null $order_id
 * @property string $source
 * @property Carbon $created_at
 * @property-read Course $course
 * @property-read User $user
 */
#[Fillable(['user_id', 'course_id', 'order_id', 'source'])]
#[UseFactory(EnrollmentFactory::class)]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
