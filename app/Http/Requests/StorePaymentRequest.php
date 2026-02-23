<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lease_id' => ['required', 'exists:leases,id'],
            'type' => ['required', 'in:rent,utilities,deposit,other'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'paid_date' => ['nullable', 'date'],
            'variable_symbol' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'in:paid,unpaid,overdue'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'lease_id.exists' => 'Selected lease does not exist.',
            'type.in' => 'Payment type must be: rent, utilities, deposit, or other.',
        ];
    }
}
