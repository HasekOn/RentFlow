<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'in:rent,utilities,deposit,other'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'due_date' => ['sometimes', 'date'],
            'paid_date' => ['nullable', 'date'],
            'variable_symbol' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'in:paid,unpaid,overdue'],
            'note' => ['nullable', 'string'],
        ];
    }
}
