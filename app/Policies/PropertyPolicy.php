<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    /**
     * Can user see list of properties?
     * Everyone who is logged in can see their relevant properties
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Can user see this specific property?
     * Landlord owns it OR tenant has active lease there
     */
    public function view(User $user, Property $property): bool
    {
        // Landlord owns this property
        if ($property->landlord_id === $user->id) {
            return true;
        }

        // Tenant has active lease for this property
        return $user->leases()
            ->where('property_id', $property->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Can user create properties?
     * Only landlords
     */
    public function create(User $user): bool
    {
        return $user->role === 'landlord';
    }

    /**
     * Can user update this property?
     * Only the owner
     */
    public function update(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    /**
     * Can user delete this property?
     * Only the owner
     */
    public function delete(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    /**
     * restore and forceDelete not needed
     */
    public function restore(User $user, Property $property): bool
    {
        return false;
    }

    public function forceDelete(User $user, Property $property): bool
    {
        return false;
    }
}
