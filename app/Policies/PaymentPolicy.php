<?php

namespace App\Policies;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

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

        // Tenant on this lease (works for both tenant and manager roles)
        if ($lease->tenant_id === $user->id) {
            return true;
        }

        // Manager manages this property — can view payments (read-only)
        if ($user->isManager()) {
            return $user->managedProperties()
                ->where('properties.id', $lease->property_id)
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isLandlord();
    }

    public function update(User $user, Payment $payment): bool
    {
        $lease = $payment->lease;

        return $lease && $lease->property && $lease->property->landlord_id === $user->id;
    }

    public function delete(User $user, Payment $payment): bool
    {
        if ($payment->status === PaymentStatus::Paid) {
            return false;
        }

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
