<?php

namespace App\Domain\Sales\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A payment provider notification. Not tenant scoped: providers call one global URL and
 * the tenant is found through the order afterwards.
 *
 * @property int $id
 * @property string $provider
 * @property string $idempotency_key
 * @property array<string, mixed> $payload
 * @property string|null $result
 * @property Carbon|null $processed_at
 */
#[Fillable(['provider', 'idempotency_key', 'payload', 'result', 'processed_at'])]
class WebhookEvent extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }
}
