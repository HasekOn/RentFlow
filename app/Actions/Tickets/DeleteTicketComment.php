<?php

namespace App\Actions\Tickets;

use App\Models\TicketComment;
use App\Support\PrivateFile;

/**
 * Smazání komentáře i s přílohou (soubor až po smazání záznamu). Autorizaci dělá volající.
 */
final class DeleteTicketComment
{
    public function handle(TicketComment $comment): void
    {
        $comment->delete();
        PrivateFile::delete($comment->attachment_path);
    }
}
