<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Catalog\Enums\LessonType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LessonRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:150'],
            'type' => [$required, Rule::enum(LessonType::class)],
            'content' => ['nullable', 'string', 'max:200000'],
            'transcript' => ['nullable', 'string', 'max:200000'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_preview' => ['sometimes', 'boolean'],
        ];
    }
}
