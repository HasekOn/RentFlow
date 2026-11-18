<?php

namespace App\Http\Requests;

use App\Enums\DocumentVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
            'document_type' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'visibility' => ['sometimes', Rule::enum(DocumentVisibility::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'Document size cannot exceed 10 MB.',
            'document_type.required' => 'Document type is required.',
            'name.required' => 'Document name is required.',
        ];
    }
}
