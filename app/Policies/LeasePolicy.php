<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\User;

class LeasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lease $lease): bool
    {
        // Landlord owns the property
        if ($lease->property && $lease->property->landlord_id === $user->id) {
            return true;
        }

        // Tenant on this lease (works for both tenant and manager roles)
        if ($lease->tenant_id === $user->id) {
            return true;
        }

        // Manager manages this property — can view lease details (read-only)
        if ($user->role === 'manager' && $lease->property) {
            return $user->managedProperties()
                ->where('properties.id', $lease->property_id)
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === 'landlord';
    }

    public function update(User $user, Lease $lease): bool
    {
        return $lease->property && $lease->property->landlord_id === $user->id;
    }

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
