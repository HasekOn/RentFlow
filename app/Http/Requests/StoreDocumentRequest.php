<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
