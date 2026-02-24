<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Notifications\TicketCreatedNotification;

class SendTicketCreatedNotification
{
    public function handle(TicketCreated $event): void
    {
        $ticket = $event->ticket;

        $ticket->load('property.landlord');

        $landlord = $ticket->property?->landlord;

        $landlord?->notify(new TicketCreatedNotification($ticket));
    }
}
