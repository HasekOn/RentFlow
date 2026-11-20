<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\Property;
use App\Models\User;

class LeasePolicy
{
    /**
     * Seznam vidí každý přihlášený, obsah omezuje Lease::visibleTo.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Vlastník nemovitosti, nájemník smlouvy, přiřazený správce u aktivní smlouvy (jedna pravda se scope visibleTo).
     */
    public function view(User $user, Lease $lease): bool
    {
        return $lease->tenant_id === $user->id
            || Lease::withTrashed()->visibleTo($user)->whereKey($lease->id)->exists();
    }

    /**
     * PDF smlouvy (kauce, kontakty obou stran): nájemník smlouvy vždy, vlastník nemovitosti jen u smlouvy, kterou
     * nájemník přijal (B-1: jinak by pozvánka s cizím nájemníkem prozradila jeho e-mail a telefon). Správce ne.
     */
    public function downloadPdf(User $user, Lease $lease): bool
    {
        return $lease->tenant_id === $user->id
            || ($lease->isAccepted() && $lease->property?->landlord_id === $user->id);
    }

    /**
     * Přijetí nebo odmítnutí pozvánky (S1): jen nájemník smlouvy (i správce, který je nájemníkem). Stav hlídá Action.
     */
    public function respond(User $user, Lease $lease): bool
    {
        return $lease->tenant_id === $user->id;
    }

    public function create(User $user, Property $property): bool
    {
        return $property->landlord_id === $user->id;
    }

    public function update(User $user, Lease $lease): bool
    {
        return $lease->property?->landlord_id === $user->id;
    }

    public function delete(User $user, Lease $lease): bool
    {
        return $lease->property?->landlord_id === $user->id;
    }
}
