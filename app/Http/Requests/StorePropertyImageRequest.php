<?php

namespace App\Http\Requests;

use App\Enums\PropertyImageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:5120'],
            'type' => ['sometimes', Rule::enum(PropertyImageType::class)],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.image' => 'File must be an image (jpg, png, gif, webp).',
            'image.max' => 'Image size cannot exceed 5 MB.',
            'type.in' => 'Image type must be: marketing, defect, or document.',
        ];
    }
}
