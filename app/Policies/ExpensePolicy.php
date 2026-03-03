<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Can user see list of expenses?
     * All authenticated users — filtered in controller by ownership/lease
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Can user see this expense?
     * Landlord who owns the property OR tenant with active lease
     */
    public function view(User $user, Expense $expense): bool
    {
        if (! $expense->property) {
            return false;
        }

        if ($expense->property->landlord_id === $user->id) {
            return true;
        }

        return $user->leases()
            ->where('property_id', $expense->property_id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Can user create expenses? Only landlords
     */
    public function create(User $user): bool
    {
        return $user->role === 'landlord';
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $expense->property && $expense->property->landlord_id === $user->id;
    }

    public function restore(User $user, Expense $expense): bool
    {
        return false;
    }

    public function forceDelete(User $user, Expense $expense): bool
    {
        return false;
    }
}
