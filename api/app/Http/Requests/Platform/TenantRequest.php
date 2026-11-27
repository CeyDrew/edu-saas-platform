<?php

namespace App\Http\Requests\Platform;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenant = $this->route('tenant');
        $creating = ! $tenant instanceof Tenant;

        return [
            'slug' => [
                Rule::requiredIf($creating), 'string', 'min:3', 'max:63', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::notIn(Tenant::RESERVED_SLUGS),
                Rule::unique('tenants', 'slug')->ignore($creating ? null : $tenant->id),
            ],
            'name' => [Rule::requiredIf($creating), 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'theme' => ['nullable', 'array'],
            'theme.primary' => ['nullable', 'hex_color'],
            'theme.accent' => ['nullable', 'hex_color'],
            'theme.font' => ['nullable', Rule::in(['sans', 'serif', 'rounded'])],
            'theme.hero' => ['nullable', Rule::in(['gradient', 'split', 'stats'])],
            'plan' => ['sometimes', Rule::in(['starter', 'pro', 'business'])],
            'commission_rate' => ['sometimes', 'numeric', 'between:0,50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
