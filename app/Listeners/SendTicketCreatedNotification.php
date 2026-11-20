<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Nový tiket dostane vlastník nemovitosti a správci k ní přiřazení, autor ne (#47; správce může tiket založit
 * ve svém bytě i v bytě, který spravuje).
 */
class SendTicketCreatedNotification
{
    public function handle(TicketCreated $event): void
    {
        $ticket = $event->ticket;
        $ticket->loadMissing(['property.landlord', 'property.managers']);

        $recipients = collect([$ticket->property?->landlord])
            ->merge($ticket->property->managers ?? [])
            ->filter(fn (?User $user) => $user !== null && $user->id !== $ticket->tenant_id)
            ->unique('id');

        Notification::send($recipients, new TicketCreatedNotification($ticket));
    }
}
