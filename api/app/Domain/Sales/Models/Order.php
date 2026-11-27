<?php

namespace App\Domain\Sales\Models;

use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\Enums\OrderType;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $number
 * @property OrderType $type
 * @property OrderStatus $status
 * @property int $subtotal
 * @property int $discount
 * @property int $total
 * @property int $platform_fee
 * @property string $currency
 * @property int|null $coupon_id
 * @property array<string, string|null>|null $billing
 * @property string|null $payment_provider
 * @property string|null $payment_token
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read Collection<int, OrderItem> $items
 */
#[Fillable(['user_id', 'number', 'type', 'subtotal', 'discount', 'total', 'currency', 'coupon_id', 'billing', 'payment_provider'])]
#[UseFactory(OrderFactory::class)]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use BelongsToTenant, HasFactory;

    protected $attributes = ['status' => 'pending', 'currency' => 'TRY', 'discount' => 0, 'platform_fee' => 0];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'billing' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }
}
