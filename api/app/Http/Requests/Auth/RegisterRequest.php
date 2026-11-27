<?php

namespace App\Http\Requests\Auth;

use App\Domain\Tenancy\TenantContext;
use CeyDrew\TrValidation\Rules\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
