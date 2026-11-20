<?php

namespace App\Http\Requests;

use App\Enums\DocumentVisibility;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', Document::MIMES, 'max:'.Document::MAX_FILE_KB],
            'document_type' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'visibility' => ['sometimes', Rule::enum(DocumentVisibility::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'Document size cannot exceed '.(Document::MAX_FILE_KB / 1024).' MB.',
            'document_type.required' => 'Document type is required.',
            'name.required' => 'Document name is required.',
        ];
    }
}
