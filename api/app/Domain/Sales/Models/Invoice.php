<?php

namespace App\Domain\Sales\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $number
 * @property string $kind
 * @property string|null $provider_reference
 * @property int $total
 * @property Carbon $issued_at
 */
#[Fillable(['order_id', 'number', 'kind', 'provider_reference', 'total', 'issued_at'])]
#[UseFactory(InvoiceFactory::class)]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }
}
