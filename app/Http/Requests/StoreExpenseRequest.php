<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', 'exists:properties,id'],
            'type' => ['required', 'in:repair,insurance,tax,maintenance,other'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'invoice_path' => ['nullable', 'string'],
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
