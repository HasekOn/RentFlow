<?php

namespace App\Http\Requests;

use App\Enums\LeaseStatus;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start_date' => ['sometimes', 'date'],
            // proti uloženému start_date kontroluje LeaseController až po autorizaci (#30)
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['sometimes', 'numeric', 'min:0', 'max:'.Limits::AMOUNT],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:'.Limits::AMOUNT],
            'utility_advances' => ['nullable', 'numeric', 'min:0', 'max:'.Limits::AMOUNT],
            'variable_symbol' => ['nullable', 'string', 'max:20', StoreLeaseRequest::uniqueVariableSymbol($this)->ignore($this->route('lease'))],
            // pending a declined nastavuje jen založení a odpověď nájemníka (S1)
            'status' => ['sometimes', Rule::enum(LeaseStatus::class)->only([LeaseStatus::Active, LeaseStatus::Ended, LeaseStatus::Terminated])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'variable_symbol.unique' => 'This variable symbol is already in use.',
        ];
    }
}
