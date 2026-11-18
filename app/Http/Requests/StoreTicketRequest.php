<?php

namespace App\Http\Requests;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', 'exists:properties,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
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
