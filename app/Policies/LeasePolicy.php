<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\User;

class LeasePolicy
{
    /**
     * Can user see list of leases?
     * Everyone logged in — controller filters the data
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Can user see this lease?
     * Landlord who owns the property OR the tenant on this lease
     */
    public function view(User $user, Lease $lease): bool
    {
        // Landlord owns the property
        if ($lease->property && $lease->property->landlord_id === $user->id) {
            return true;
        }

        // Tenant on this lease
        return $lease->tenant_id === $user->id;
    }

    /**
     * Can user create leases?
     * Only landlords
     */
    public function create(User $user): bool
    {
        return $user->role === 'landlord';
    }

    /**
     * Can user update this lease?
     * Only landlord who owns the property
     */
    public function update(User $user, Lease $lease): bool
    {
        return $lease->property && $lease->property->landlord_id === $user->id;
    }

    /**
     * Can user delete this lease?
     * Only landlord who owns the property
     */
    public function delete(User $user, Lease $lease): bool
    {
        return $lease->property && $lease->property->landlord_id === $user->id;
    }

    public function restore(User $user, Lease $lease): bool
    {
        return false;
    }

    public function forceDelete(User $user, Lease $lease): bool
    {
        return false;
    }
}
