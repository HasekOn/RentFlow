<?php

namespace App\Actions\Tickets;

use App\Models\TicketImage;
use App\Support\PrivateFile;

/**
 * Smazání fotky tiketu i se souborem (soubor až po smazání záznamu). Autorizaci dělá volající.
 */
final class DeleteTicketImage
{
    public function handle(TicketImage $image): void
    {
        $image->delete();
        PrivateFile::delete($image->image_path);
    }
}
