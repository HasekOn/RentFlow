<?php

namespace App\Policies;

use App\Models\Notice;
use App\Models\User;

class NoticePolicy
{
    /**
     * Can user see notices?
     * Everyone — tenants need to read announcements
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Can user see this notice?
     * Landlord owns the property OR tenant has active lease there
     */
    public function view(User $user, Notice $notice): bool
    {
        $property = $notice->property;

        if (!$property) {
            return false;
        }

        // Landlord owns the property
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
     * Can user create notices?
     * Only landlords
     */
    public function create(User $user): bool
    {
        return $user->role === 'landlord';
    }

    /**
     * Can user delete this notice?
     * Only landlord who owns the property
     */
    public function delete(User $user, Notice $notice): bool
    {
        return $this->update($user, $notice);
    }

    /**
     * Can user update this notice?
     * Only landlord who owns the property
     */
    public function update(User $user, Notice $notice): bool
    {
        return $notice->property && $notice->property->landlord_id === $user->id;
    }

    public function restore(User $user, Notice $notice): bool
    {
        return false;
    }

    public function forceDelete(User $user, Notice $notice): bool
    {
        return false;
    }
}
