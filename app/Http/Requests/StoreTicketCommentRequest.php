<?php

namespace App\Http\Requests;

use App\Support\PrivateFile;
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
            'attachment' => ['sometimes', 'file', PrivateFile::IMAGE_MIMES, 'max:'.PrivateFile::IMAGE_MAX_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Comment message cannot be empty.',
        ];
    }
}
