<?php

namespace App\Listeners;

use App\Events\TicketResolved;
use App\Notifications\TicketResolvedNotification;

class SendTicketResolvedNotification
{
    public function handle(TicketResolved $event): void
    {
        $ticket = $event->ticket;
        
        $ticket->load('tenant');

        if ($ticket->tenant) {
            $ticket->tenant->notify(new TicketResolvedNotification($ticket));
        }
    }
}
