<?php

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Enums\Role;
use App\Events\LeaseCreated;
use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nová smlouva je pozvánka (`pending`, S1): aktivní a obsazující nemovitost je až po přijetí nájemníkem
 * (AcceptLease), překryv aktivních smluv se proto kontroluje až tam. Nájemník se hledá podle přesného e-mailu
 * (B-3); neexistující účet i účet pronajímatele (#22) vrací stejnou hlášku, aby nešlo zjistit, kdo má účet.
 * Pozvánka nájemníkovi (LeaseCreated) odchází po commitu. Autorizaci dělá volající.
 */
final class CreateLease
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole (property_id odpovídá $property, tenant_email)
     */
    public function handle(Property $property, array $data): Lease
    {
        return DB::transaction(function () use ($property, $data): Lease {
            // rekonstrukce před hledáním účtu: jinak by klíč chyby prozradil, že e-mail patří nájemníkovi
            if ($property->status === PropertyStatus::Renovation) {
                throw ValidationException::withMessages([
                    'property_id' => __('Cannot create lease for a property under renovation.'),
                ]);
            }

            $tenant = User::query()
                ->where('email', $data['tenant_email'])
                ->whereIn('role', [Role::Tenant, Role::Manager])
                ->first(['id']);
            if ($tenant === null) {
                throw ValidationException::withMessages([
                    'tenant_email' => __('This email cannot be invited. Ask the tenant to register with it first.'),
                ]);
            }
            unset($data['tenant_email']);
            $data['tenant_id'] = $tenant->id;

            $duplicate = Lease::query()
                ->where('property_id', $property->id)
                ->where('tenant_id', $data['tenant_id'])
                ->whereIn('status', [LeaseStatus::Pending, LeaseStatus::Active])
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'tenant_email' => __('An active lease or invitation already exists for this tenant on this property.'),
                ]);
            }

            $lease = Lease::query()->create([...$data, 'property_id' => $property->id, 'status' => LeaseStatus::Pending]);
            $lease->setRelation('property', $property);

            LeaseCreated::dispatch($lease);

            return $lease;
        });
    }
}
