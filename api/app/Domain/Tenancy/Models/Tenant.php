<?php

namespace App\Domain\Tenancy\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * An education brand. Every tenant-owned row carries this model's id.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $tagline
 * @property array<string, string>|null $theme
 * @property string $plan
 * @property string $commission_rate
 * @property bool $is_active
 */
#[Fillable(['slug', 'name', 'tagline', 'theme', 'plan', 'commission_rate', 'is_active'])]
#[UseFactory(TenantFactory::class)]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    public const array RESERVED_SLUGS = ['www', 'admin', 'api', 'app', 'mail'];

    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'is_active' => 'boolean',
            'commission_rate' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // The resolver caches slug lookups; drop them when a brand changes or is closed.
        static::saved(fn (Tenant $tenant) => Cache::forget("tenant-slug:{$tenant->getOriginal('slug')}"));
        static::saved(fn (Tenant $tenant) => Cache::forget("tenant-slug:{$tenant->slug}"));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
