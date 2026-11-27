<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Enums\CourseLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'min:3', 'max:150'],
            'slug' => ['sometimes', 'string', 'max:160', 'alpha_dash:ascii'],
            'summary' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:20000'],
            'category' => ['nullable', 'string', 'max:60'],
            'level' => ['sometimes', Rule::enum(CourseLevel::class)],
            'price' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'compare_at_price' => ['nullable', 'integer', 'gt:price'],
            'included_in_subscription' => ['sometimes', 'boolean'],
        ];
    }
}
