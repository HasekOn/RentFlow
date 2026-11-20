<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;

/**
 * Komentáře tiketu čte každý, kdo vidí tiket (TicketPolicy::view, #10), píše jen kdo smí přispívat
 * (TicketPolicy::contribute: autor bez aktivní smlouvy jen u otevřeného tiketu), maže jen autor komentáře.
 */
class TicketCommentPolicy
{
    public function viewAny(User $user, Ticket $ticket): bool
    {
        return $user->can('view', $ticket);
    }

    public function create(User $user, Ticket $ticket): bool
    {
        return $user->can('contribute', $ticket);
    }

    public function delete(User $user, TicketComment $comment): bool
    {
        return $comment->user_id === $user->id;
    }
}
