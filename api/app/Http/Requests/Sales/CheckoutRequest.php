<?php

namespace App\Http\Requests\Sales;

use CeyDrew\TrValidation\Rules\Tckn;
use CeyDrew\TrValidation\Rules\Vkn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Checkout for a course or a subscription plan, with invoice details validated by
 * checksum (TCKN for individuals, VKN for companies).
 */
class CheckoutRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $quoteOnly = $this->routeIs('checkout.quote');

        return [
            'course' => ['required_without:plan_id', 'nullable', 'string'],
            'plan_id' => ['required_without:course', 'nullable', 'integer'],
            'coupon' => ['nullable', 'string', 'max:40'],

            'billing' => [$quoteOnly ? 'nullable' : 'required', 'array'],
            'billing.type' => [$quoteOnly ? 'nullable' : 'required', Rule::in(['individual', 'corporate'])],
            'billing.name' => ['required_if:billing.type,individual', 'nullable', 'string', 'max:120'],
            'billing.tckn' => ['required_if:billing.type,individual', 'nullable', new Tckn],
            'billing.company' => ['required_if:billing.type,corporate', 'nullable', 'string', 'max:200'],
            'billing.vkn' => ['required_if:billing.type,corporate', 'nullable', new Vkn],
            'billing.tax_office' => ['required_if:billing.type,corporate', 'nullable', 'string', 'max:100'],
            'billing.address' => [$quoteOnly ? 'nullable' : 'required', 'string', 'max:300'],
            'billing.city' => [$quoteOnly ? 'nullable' : 'required', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function billing(): array
    {
        /** @var array<string, string|null> $billing */
        $billing = $this->validated('billing') ?? [];

        if (($billing['type'] ?? null) === 'corporate') {
            $billing['name'] = $billing['company'] ?? null;
            unset($billing['tckn']);
        } else {
            unset($billing['company'], $billing['vkn'], $billing['tax_office']);
        }

        return $billing;
    }
}
