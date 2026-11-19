<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Ticket;

/**
 * Načtení tiketu z URL i s nemovitostí pro policy (find + 404). Autorizaci dělá volající.
 */
trait FindsTicket
{
    protected function findTicket(string $id): Ticket
    {
        $ticket = Ticket::query()->with('property')->find($id);
        if ($ticket === null) {
            abort(404);
        }

        return $ticket;
    }
}
