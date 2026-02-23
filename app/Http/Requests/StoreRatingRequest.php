<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'in:apartment_condition,communication,rules,overall'],
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.in' => 'Category must be: apartment_condition, communication, rules, or overall.',
            'score.min' => 'Score must be between 1 and 5.',
            'score.max' => 'Score must be between 1 and 5.',
        ];
    }
}
