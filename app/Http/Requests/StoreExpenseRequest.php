<?php

namespace App\Http\Requests;

use App\Enums\ExpenseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
            'type' => ['required', Rule::enum(ExpenseType::class)],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'property_id.exists' => 'Selected property does not exist.',
            'type.in' => 'Expense type must be: repair, insurance, tax, maintenance, or other.',
        ];
    }
}
