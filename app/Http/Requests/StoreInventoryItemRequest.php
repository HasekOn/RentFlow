<?php

namespace App\Http\Requests;

use App\Enums\InventoryCondition;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'condition' => ['sometimes', Rule::enum(InventoryCondition::class)],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:'.Limits::AMOUNT],
            'note' => ['nullable', 'string', 'max:'.Limits::TEXT],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Item name is required.',
            'condition.in' => 'Condition must be: new, good, fair, poor, or broken.',
        ];
    }
}
