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
            'message' => ['required', 'string', 'min:1'],
            'attachment' => ['sometimes', 'file', 'image', 'max:5120'], // max 5MB
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Comment message cannot be empty.',
        ];
    }
}
