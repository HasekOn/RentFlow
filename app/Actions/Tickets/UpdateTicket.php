<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Events\TicketResolved;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Úprava tiketu (#23, #54). Řešitel jen správce přiřazený k nemovitosti tiketu. `resolved_at` a upozornění
 * autorovi (TicketResolved, po commitu) jen při skutečném přechodu na vyřešený; znovuotevření `resolved_at` smaže.
 * Autorizaci (kdo smí co měnit) dělá volající.
 */
final class UpdateTicket
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole
     */
    public function handle(Ticket $ticket, array $data): Ticket
    {
        $assignee = $data['assigned_to'] ?? null;
        if ($assignee !== null && ! $ticket->property?->managers()->whereKey($assignee)->exists()) {
            throw ValidationException::withMessages([
                'assigned_to' => __('Assignee must be a manager assigned to the ticket property.'),
            ]);
        }

        return DB::transaction(function () use ($ticket, $data): Ticket {
            $wasResolved = $ticket->status === TicketStatus::Resolved;
            $ticket->fill($data);
            $resolved = $ticket->status === TicketStatus::Resolved;

            if ($resolved && ! $wasResolved) {
                $ticket->resolved_at = now();
            } elseif (! $resolved) {
                $ticket->resolved_at = null;
            }
            $ticket->save();

            if ($resolved && ! $wasResolved) {
                TicketResolved::dispatch($ticket);
            }

            return $ticket;
        });
    }
}
