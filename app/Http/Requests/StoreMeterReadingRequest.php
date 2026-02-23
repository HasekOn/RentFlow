<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reading_value' => ['required', 'numeric', 'min:0'],
            'reading_date' => ['required', 'date'],
            'photo_proof' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'reading_value.min' => 'Reading value cannot be negative.',
            'reading_date.required' => 'Reading date is required.',
        ];
    }
}
