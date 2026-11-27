<?php

namespace App\Domain\Sales\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string $provider
 * @property string|null $provider_payment_id
 * @property string $status
 * @property int $amount
 * @property array<string, mixed>|null $payload
 */
#[Fillable(['order_id', 'provider', 'provider_payment_id', 'status', 'amount', 'payload'])]
#[UseFactory(PaymentFactory::class)]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
