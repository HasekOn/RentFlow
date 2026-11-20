<?php

namespace App\Http\Requests;

use App\Enums\ExpenseType;
use App\Support\Limits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(ExpenseType::class)],
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:'.Limits::AMOUNT],
            'expense_date' => ['sometimes', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
