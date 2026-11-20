<?php

namespace App\Http\Requests;

use App\Models\Property;
use App\Support\Limits;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreLeaseRequest extends FormRequest
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
            // nájemník podle přesného e-mailu jako users/lookup, ne podle id (B-3); účet a roli ověří CreateLease
            'tenant_email' => ['required', 'string', 'email', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['required', 'numeric', 'min:0', 'max:'.Limits::AMOUNT],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:'.Limits::AMOUNT],
            'utility_advances' => ['nullable', 'numeric', 'min:0', 'max:'.Limits::AMOUNT],
            // unikátní jen mezi smlouvami nemovitostí téhož pronajímatele, jinak by šlo zjišťovat cizí VS (S10)
            'variable_symbol' => ['nullable', 'string', 'max:20', self::uniqueVariableSymbol($this)],
        ];
    }

    /**
     * VS je unikátní mezi smlouvami nemovitostí přihlášeného pronajímatele (i smazaných). Smlouvy zakládá a upravuje
     * jen vlastník nemovitosti, ostatní 403 vrátí policy.
     */
    public static function uniqueVariableSymbol(FormRequest $request): Unique
    {
        return Rule::unique('leases', 'variable_symbol')
            ->where(fn (Builder $query) => $query->whereIn('property_id', Property::withTrashed()
                ->where('landlord_id', $request->user()?->getAuthIdentifier())
                ->select('id')));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'property_id.exists' => 'Selected property does not exist.',
            'end_date.after' => 'End date must be after start date.',
            'variable_symbol.unique' => 'This variable symbol is already in use.',
        ];
    }
}
