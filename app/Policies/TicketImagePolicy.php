<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\TicketImage;
use App\Models\User;

/**
 * Fotky tiketu vidí každý, kdo vidí tiket (TicketPolicy::view, #10), nahrává kdo smí přispívat
 * (TicketPolicy::contribute). Maže ten, kdo fotku nahrál,
 * nebo vlastník nemovitosti tiketu (dřív kterýkoli pronajímatel).
 */
class TicketImagePolicy
{
    public function viewAny(User $user, Ticket $ticket): bool
    {
        return $user->can('view', $ticket);
    }

    public function create(User $user, Ticket $ticket): bool
    {
        return $user->can('contribute', $ticket);
    }

    public function delete(User $user, TicketImage $image): bool
    {
        return $image->uploaded_by === $user->id || $image->ticket?->property?->landlord_id === $user->id;
    }
}
