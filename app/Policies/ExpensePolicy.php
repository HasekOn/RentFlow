<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\Property;
use App\Models\User;

/**
 * Náklady jen vlastník nemovitosti (matice: správce ani nájemník finance nevidí, #16).
 */
class ExpensePolicy
{
    /**
     * Seznam jen pro pronajímatele, obsah omezuje Expense::visibleTo.
     */
    public function viewAny(User $user): bool
    {
        return $user->isLandlord();
    }

    public function view(User $user, Expense $expense): bool
    {
        return $expense->property?->landlord_id === $user->id;
    }

    public function create(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    public function update(User $user, Expense $expense): bool
    {
        return $expense->property?->landlord_id === $user->id;
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $expense->property?->landlord_id === $user->id;
    }
}
