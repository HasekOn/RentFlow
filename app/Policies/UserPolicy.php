<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Seznam lidí (matice „View People“): pronajímatel a správce s aspoň jedním přiřazením, nájemník ne.
     */
    public function viewAny(User $user): bool
    {
        return $user->isLandlord() || $user->managedProperties()->exists();
    }

    /**
     * Detail osoby, trust score a hodnocení: jen ten, koho viewer vidí ve scope visibleTo (jedna pravda se seznamem).
     */
    public function view(User $user, User $model): bool
    {
        return $user->is($model) || User::query()->visibleTo($user)->whereKey($model->id)->exists();
    }

    /**
     * Vyhledání účtu podle přesného e-mailu pro novou smlouvu.
     */
    public function lookup(User $user): bool
    {
        return $user->isLandlord();
    }

    /**
     * Povýšení na správce (přiřazení k vlastní nemovitosti, #14): jen pronajímatel a jen koho už vidí (nájemníci
     * jeho smluv, jeho správci), ne pronajímatel ani on sám. Cizí účet podle id → 403, ať id neprozradí kontakty.
     */
    public function promote(User $user, User $model): bool
    {
        return $user->isLandlord()
            && ! $model->isLandlord()
            && ! $user->is($model)
            && $this->view($user, $model);
    }

    /**
     * Sesazení odebere jen přiřazení k nemovitostem tohoto pronajímatele, stejné pravidlo jako povýšení.
     */
    public function demote(User $user, User $model): bool
    {
        return $this->promote($user, $model);
    }
}
