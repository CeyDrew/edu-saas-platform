<?php

namespace App\Http\Resources;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full brand record for the platform panel.
 *
 * @mixin Tenant
 */
class PlatformTenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'theme' => $this->theme ?? [],
            'plan' => $this->plan,
            'commission_rate' => (float) $this->commission_rate,
            'is_active' => $this->is_active,
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
