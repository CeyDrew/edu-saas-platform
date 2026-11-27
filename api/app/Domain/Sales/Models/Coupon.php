<?php

namespace App\Domain\Sales\Models;

use App\Domain\Sales\Enums\CouponType;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property CouponType $type
 * @property int $value
 * @property int|null $max_uses
 * @property int $used_count
 * @property int $min_amount
 * @property Carbon|null $expires_at
 * @property bool $is_active
 */
#[Fillable(['code', 'type', 'value', 'max_uses', 'min_amount', 'expires_at', 'is_active'])]
#[UseFactory(CouponFactory::class)]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use BelongsToTenant, HasFactory;

    protected $attributes = ['used_count' => 0, 'min_amount' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Why the coupon cannot be used for this amount, or null when it can.
     */
    public function rejectionReason(int $amount): ?string
    {
        return match (true) {
            ! $this->is_active => 'Kupon aktif değil.',
            $this->expires_at !== null && $this->expires_at->isPast() => 'Kuponun süresi dolmuş.',
            $this->max_uses !== null && $this->used_count >= $this->max_uses => 'Kupon kullanım limiti doldu.',
            $amount < $this->min_amount => 'Sepet tutarı bu kupon için yetersiz.',
            default => null,
        };
    }

    public function discountFor(int $amount): int
    {
        $discount = $this->type === CouponType::Percent
            ? intdiv($amount * min($this->value, 100), 100)
            : $this->value;

        return min($discount, $amount);
    }
}
