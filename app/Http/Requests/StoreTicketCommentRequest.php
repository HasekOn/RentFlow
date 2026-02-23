<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string'],
            'attachment_path' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Comment message cannot be empty.',
        ];
    }
}
