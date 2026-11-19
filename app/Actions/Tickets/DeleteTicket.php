<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;

/**
 * Smazání (soft delete) tiketu. Komentáře a fotky zůstávají kvůli obnově. Autorizaci dělá volající.
 */
final class DeleteTicket
{
    public function handle(Ticket $ticket): void
    {
        $ticket->delete();
    }
}
