<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Can user see list of expenses?
     * Only landlords — tenants and managers don't see finances
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'landlord';
    }

    /**
     * Can user see this expense?
     * Only landlord who owns the property
     */
    public function view(User $user, Expense $expense): bool
    {
        return $expense->property && $expense->property->landlord_id === $user->id;
    }

    /**
     * Can user create expenses?
     * Only landlords
     */
    public function create(User $user): bool
    {
        return $user->role === 'landlord';
    }

    /**
     * Can user delete this expense?
     * Only landlord who owns the property
     */
    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }

    /**
     * Can user update this expense?
     * Only landlord who owns the property
     */
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
