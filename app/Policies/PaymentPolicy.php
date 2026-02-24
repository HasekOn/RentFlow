<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Can user see list of payments?
     * Landlords and tenants — controller filters data
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Can user see this payment?
     * Landlord who owns the property OR tenant on this lease
     */
    public function view(User $user, Payment $payment): bool
    {
        $lease = $payment->lease;

        if (! $lease || ! $lease->property) {
            return false;
        }

        // Landlord owns the property
        if ($lease->property->landlord_id === $user->id) {
            return true;
        }

        // Tenant on this lease
        return $lease->tenant_id === $user->id;
    }

    /**
     * Can user create payments?
     * Only landlords
     */
    public function create(User $user): bool
    {
        return $user->role === 'landlord';
    }

    /**
     * Can user delete this payment?
     * Only landlord who owns the property
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $this->update($user, $payment);
    }

    /**
     * Can user update this payment? (including mark-paid)
     * Only landlord who owns the property
     */
    public function update(User $user, Payment $payment): bool
    {
        $lease = $payment->lease;

        return $lease && $lease->property && $lease->property->landlord_id === $user->id;
    }

    public function restore(User $user, Payment $payment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }
}
