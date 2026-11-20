<?php

namespace App\Policies;

use App\Enums\LeaseStatus;
use App\Enums\PaymentType;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;

/**
 * Platby vidí vlastník nemovitosti, nájemník smlouvy a přiřazený správce u aktivních smluv (Payment::visibleTo).
 * Zaplacení potvrzuje vlastník (i u ukončené smlouvy) a přiřazený správce (jen aktivní), zakládá, upravuje, maže, generuje a importuje jen vlastník.
 */
class PaymentPolicy
{
    /**
     * Seznam vidí každý přihlášený, obsah omezuje Payment::visibleTo.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payment $payment): bool
    {
        return Payment::query()->visibleTo($user)->whereKey($payment->id)->exists();
    }

    /**
     * Platba jen na smlouvu vlastní nemovitosti (#12).
     */
    public function create(User $user, Lease $lease): bool
    {
        return $lease->property?->landlord_id === $user->id;
    }

    /**
     * Ruční potvrzení platby: vlastník v jakémkoli stavu smlouvy (poslední nájem a nedoplatky chodí po konci),
     * správce přiřazený k nemovitosti jen u aktivní smlouvy. Nikdy ne nájemník smlouvy, tedy ani správce svou platbu.
     */
    public function markPaid(User $user, Payment $payment): bool
    {
        $lease = $payment->lease;
        $property = $lease?->property;
        if ($lease === null || $property === null || $lease->tenant_id === $user->id) {
            return false;
        }

        // kauci správce nevidí ani nepotvrzuje (S5)
        return $property->landlord_id === $user->id
            || ($lease->status === LeaseStatus::Active
                && $payment->type !== PaymentType::Deposit
                && $property->managers()->whereKey($user->id)->exists());
    }

    public function update(User $user, Payment $payment): bool
    {
        return $payment->lease?->property?->landlord_id === $user->id;
    }

    /**
     * Jen oprávnění; zaplacenou platbu odmítne controller jako 422 (stav, ne oprávnění, #41).
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $payment->lease?->property?->landlord_id === $user->id;
    }

    /**
     * Generování měsíčních plateb a CSV import: jen pronajímatel, obojí pracuje jen se smlouvami jeho nemovitostí.
     */
    public function generate(User $user): bool
    {
        return $user->isLandlord();
    }

    public function import(User $user): bool
    {
        return $user->isLandlord();
    }
}
