<?php

namespace App\Domain\Sales\Models;

use App\Domain\Sales\Enums\SubscriptionStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\SubscriptionFactory;
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
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property Carbon $current_period_end
 * @property int $failed_attempts
 * @property Carbon|null $next_attempt_at
 * @property string|null $card_token
 * @property Carbon|null $cancelled_at
 * @property-read SubscriptionPlan $plan
 * @property-read User $user
 */
#[Fillable(['user_id', 'plan_id', 'status', 'current_period_end', 'card_token'])]
#[UseFactory(SubscriptionFactory::class)]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToTenant, HasFactory;

    public const int MAX_FAILED_ATTEMPTS = 3;

    protected $attributes = ['status' => 'active', 'failed_attempts' => 0];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_end' => 'datetime',
            'next_attempt_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Past-due subscriptions keep access while renewal is retried; suspended ones do not.
     */
    public function grantsAccess(): bool
    {
        return in_array($this->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)
            || ($this->status === SubscriptionStatus::Cancelled && $this->current_period_end->isFuture());
    }
}
