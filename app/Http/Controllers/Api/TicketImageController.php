<?php

namespace App\Http\Controllers\Api;

use App\Actions\Tickets\AddTicketImage;
use App\Actions\Tickets\DeleteTicketImage;
use App\Http\Controllers\Api\Concerns\FindsTicket;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketImageResource;
use App\Models\TicketImage;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketImageController extends Controller
{
    use FindsTicket;

    public function index(string $ticketId): JsonResponse
    {
        $ticket = $this->findTicket($ticketId);
        $this->authorize('viewAny', [TicketImage::class, $ticket]);

        $images = $ticket->images()
            ->with('uploader')
            ->orderBy('created_at')
            ->get();

        return response()->json(TicketImageResource::collection($images));
    }

    public function store(Request $request, string $ticketId, AddTicketImage $add): JsonResponse
    {
        $ticket = $this->findTicket($ticketId);
        $this->authorize('create', [TicketImage::class, $ticket]);

        $request->validate([
            'image' => ['required', 'file', PrivateFile::IMAGE_MIMES, 'max:'.PrivateFile::IMAGE_MAX_KB],
            'description' => ['sometimes', 'string', 'max:255'],
        ]);

        $image = $add->handle($ticket, $this->currentUser($request), $request->file('image'), $request->input('description'));

        $image->load('uploader');

        return response()->json(new TicketImageResource($image), 201);
    }

    public function file(string $ticketId, string $imageId): StreamedResponse
    {
        $image = TicketImage::query()->with('ticket.property')->where('ticket_id', $ticketId)->find($imageId);
        if ($image === null || $image->ticket === null) {
            abort(404);
        }

        $this->authorize('view', $image->ticket);

        return PrivateFile::download($image->image_path, basename($image->image_path));
    }

    public function destroy(string $ticketId, string $imageId, DeleteTicketImage $delete): JsonResponse
    {
        $image = TicketImage::query()->with('ticket.property')->where('ticket_id', $ticketId)->find($imageId);
        if ($image === null) {
            abort(404);
        }
        $this->authorize('delete', $image);

        $delete->handle($image);

        return response()->json(['message' => 'Image deleted successfully.']);
    }
}
