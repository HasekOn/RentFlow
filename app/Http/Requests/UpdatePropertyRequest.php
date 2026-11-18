<?php

namespace App\Http\Requests;

use App\Enums\PropertyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address' => ['sometimes', 'string', 'max:255'],
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
}
