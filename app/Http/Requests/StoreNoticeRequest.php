<?php

namespace App\Http\Requests;

use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;

class StoreNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:'.Limits::TEXT],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Notice title is required.',
            'content.required' => 'Notice content is required.',
        ];
    }
}
