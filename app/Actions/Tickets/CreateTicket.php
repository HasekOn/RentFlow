<?php

namespace App\Actions\Tickets;

use App\Events\TicketCreated;
use App\Models\Property;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Nový tiket, autorem je ten, kdo ho zakládá. Upozornění (TicketCreated) odchází až po commitu. Autorizaci dělá
 * volající.
 */
final class CreateTicket
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole (property_id odpovídá $property)
     */
    public function handle(Property $property, User $author, array $data): Ticket
    {
        return DB::transaction(function () use ($property, $author, $data): Ticket {
            $ticket = Ticket::query()->create([...$data, 'property_id' => $property->id, 'tenant_id' => $author->id]);
            $ticket->setRelation('property', $property);

            TicketCreated::dispatch($ticket);

            return $ticket;
        });
    }
}
