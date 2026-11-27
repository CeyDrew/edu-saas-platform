<?php

namespace App\Http\Requests\Brand;

use App\Domain\Identity\Role;
use App\Domain\Tenancy\TenantContext;
use CeyDrew\TrValidation\Rules\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreMemberRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'email', 'max:190',
                Rule::unique('users')->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'phone' => ['nullable', Phone::mobile()],
            'password' => ['required', Password::min(8)],
            // Brand admins can add staff; the platform role is never assignable from a brand.
            'role' => ['required', Rule::in([Role::BrandAdmin->value, Role::Instructor->value, Role::Student->value])],
        ];
    }
}
