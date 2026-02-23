<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'property_id' => ['required', 'exists:properties,id'],
            'tenant_id' => ['required', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'utility_advances' => ['nullable', 'numeric', 'min:0'],
            'variable_symbol' => ['nullable', 'string', 'max:20', 'unique:leases'],
        ];
    }

    public function messages(): array
    {
        return [
            'property_id.exists' => 'Selected property does not exist.',
            'tenant_id.exists' => 'Selected tenant does not exist.',
            'end_date.after' => 'End date must be after start date.',
            'variable_symbol.unique' => 'This variable symbol is already in use.',
        ];
    }
}
