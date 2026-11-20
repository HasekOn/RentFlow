<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(PaymentType::class)],
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:'.Limits::AMOUNT],
            'due_date' => ['sometimes', 'date'],
            'paid_date' => ['nullable', 'date'],
            'variable_symbol' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', Rule::enum(PaymentStatus::class)],
            'note' => ['nullable', 'string', 'max:'.Limits::TEXT],
        ];
    }
}
