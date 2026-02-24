<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['sometimes', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'utility_advances' => ['nullable', 'numeric', 'min:0'],
            'variable_symbol' => ['nullable', 'string', 'max:20', 'unique:leases,variable_symbol,'.$this->route('lease')],
            'status' => ['sometimes', 'in:active,ended,terminated'],
        ];
    }
}
