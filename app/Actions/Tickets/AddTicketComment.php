<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\PrivateFile;
use Illuminate\Http\UploadedFile;

/**
 * Komentář k tiketu, volitelně s přílohou na privátním disku. Autorizaci a validaci souboru dělá volající.
 */
final class AddTicketComment
{
    public function handle(Ticket $ticket, User $author, string $message, ?UploadedFile $attachment = null): TicketComment
    {
        $create = fn (?string $path) => TicketComment::query()->create([
            'message' => $message,
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'attachment_path' => $path,
        ]);

        return $attachment === null ? $create(null) : PrivateFile::storeWith($attachment, 'tickets/'.$ticket->id, $create);
    }
}
