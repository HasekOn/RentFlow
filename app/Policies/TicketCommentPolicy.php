<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;

/**
 * Komentáře tiketu čte a píše každý, kdo vidí tiket (TicketPolicy::view, #10), maže jen autor komentáře.
 */
class TicketCommentPolicy
{
    public function viewAny(User $user, Ticket $ticket): bool
    {
        return $user->can('view', $ticket);
    }

    public function create(User $user, Ticket $ticket): bool
    {
        return $user->can('view', $ticket);
    }

    public function delete(User $user, TicketComment $comment): bool
    {
        return $comment->user_id === $user->id;
    }
}
