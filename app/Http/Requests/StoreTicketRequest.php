<?php

namespace App\Http\Requests;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'exists:properties,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:'.Limits::TEXT],
            'category' => ['sometimes', Rule::enum(TicketCategory::class)],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'description.min' => 'Please describe the issue in at least 10 characters.',
            'category.in' => 'Category must be: plumbing, electrical, heating, structural, appliance, or other.',
            'priority.in' => 'Priority must be: low, medium, high, or urgent.',
        ];
    }
}
