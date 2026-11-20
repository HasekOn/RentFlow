<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lease_id' => ['required', 'exists:leases,id'],
            'type' => ['required', Rule::enum(PaymentType::class)],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'paid_date' => ['nullable', 'date'],
            'variable_symbol' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', Rule::enum(PaymentStatus::class)],
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
