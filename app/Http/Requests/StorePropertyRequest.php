<?php

namespace App\Http\Requests;

use App\Enums\PropertyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handled by Policy
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'size' => ['nullable', 'numeric', 'min:1'],
            'disposition' => ['nullable', 'string', 'max:20'],
            'floor' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(PropertyStatus::class)],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'address.required' => 'Property address is required.',
            'size.min' => 'Property size must be at least 1 m².',
            'status.in' => 'Status must be: available, occupied, or renovation.',
        ];
    }
}
