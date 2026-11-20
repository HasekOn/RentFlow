<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\User;

/**
 * Tikety podle vztahu k nemovitosti, ne podle role (#24): vidí autor a kdo vidí nemovitost (Ticket::visibleTo),
 * zakládá vlastník, přiřazený správce a nájemník s aktivní smlouvou (#11), řeší vlastník a přiřazený správce,
 * autor ve stavu new upraví jen název a popis (#46), maže vlastník.
 */
class TicketPolicy
{
    /**
     * Seznam vidí každý přihlášený, obsah omezuje Ticket::visibleTo.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->tenant_id === $user->id
            || Ticket::query()->visibleTo($user)->whereKey($ticket->id)->exists();
    }

    public function create(User $user, Property $property): bool
    {
        return $user->can('view', $property);
    }

    /**
     * Komentáře a fotky: kdo vidí nemovitost, vždy; autor bez aktivní smlouvy jen u otevřeného tiketu
     * (new, in_progress), vyřešený nebo zamítnutý už jen čte.
     */
    public function contribute(User $user, Ticket $ticket): bool
    {
        if (! $this->view($user, $ticket)) {
            return false;
        }

        return in_array($ticket->status, [TicketStatus::New, TicketStatus::InProgress], true)
            || ($ticket->property !== null && $user->can('view', $ticket->property));
    }

    /**
     * Všechna pole včetně stavu a řešitele (matice „Resolve ticket“).
     */
    public function update(User $user, Ticket $ticket): bool
    {
        $property = $ticket->property;

        return $property !== null
            && ($property->landlord_id === $user->id || $property->managers()->whereKey($user->id)->exists());
    }

    /**
     * Jen název a popis: kdo tiket řeší, nebo autor, dokud je tiket nový (#46).
     */
    public function updateDetails(User $user, Ticket $ticket): bool
    {
        return ($ticket->tenant_id === $user->id && $ticket->status === TicketStatus::New)
            || $this->update($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $ticket->property?->landlord_id === $user->id;
    }

    public function restore(User $user, Ticket $ticket): bool
    {
        return false;
    }

    public function forceDelete(User $user, Ticket $ticket): bool
    {
        return false;
    }
}
