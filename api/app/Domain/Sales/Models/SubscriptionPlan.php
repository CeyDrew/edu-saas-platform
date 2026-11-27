<?php

namespace App\Domain\Sales\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $price
 * @property int $interval_months
 * @property bool $is_active
 */
#[Fillable(['name', 'description', 'price', 'interval_months', 'is_active'])]
#[UseFactory(SubscriptionPlanFactory::class)]
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
