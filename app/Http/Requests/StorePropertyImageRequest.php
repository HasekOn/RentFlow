<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'type' => ['sometimes', 'in:marketing,defect,document'],
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
