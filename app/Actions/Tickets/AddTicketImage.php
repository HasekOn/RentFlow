<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\TicketImage;
use App\Models\User;
use App\Support\PrivateFile;
use Illuminate\Http\UploadedFile;

/**
 * Fotka k tiketu na privátní disk. Autorizaci a validaci souboru dělá volající.
 */
final class AddTicketImage
{
    public function handle(Ticket $ticket, User $uploader, UploadedFile $image, ?string $description = null): TicketImage
    {
        return PrivateFile::storeWith($image, 'tickets/'.$ticket->id.'/images', fn (string $path) => TicketImage::query()->create([
            'ticket_id' => $ticket->id,
            'uploaded_by' => $uploader->id,
            'image_path' => $path,
            'description' => $description,
        ]));
    }
}
