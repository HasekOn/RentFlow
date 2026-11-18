<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\Property;
use App\Models\User;

class LeasePolicy
{
    /**
     * Seznam vidí každý přihlášený, obsah omezuje Lease::visibleTo.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Vlastník nemovitosti, nájemník smlouvy, přiřazený správce u aktivní smlouvy (jedna pravda se scope visibleTo).
     */
    public function view(User $user, Lease $lease): bool
    {
        return $lease->tenant_id === $user->id
            || Lease::withTrashed()->visibleTo($user)->whereKey($lease->id)->exists();
    }

    /**
     * Kauce a PDF smlouvy: jen vlastník nemovitosti a nájemník smlouvy. Přiřazený správce vidí u aktivních smluv
     * nájemné, zálohy, VS a platby (LeaseResource), kauci a PDF ne.
     */
    public function viewFinance(User $user, Lease $lease): bool
    {
        return $lease->tenant_id === $user->id || $lease->property?->landlord_id === $user->id;
    }

    public function create(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    public function update(User $user, Lease $lease): bool
    {
        return $lease->property?->landlord_id === $user->id;
    }

    public function delete(User $user, Lease $lease): bool
    {
        return $lease->property?->landlord_id === $user->id;
    }
}
