<?php

namespace App\Http\Controllers\Api;

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

    public function store(Request $request, string $ticketId): JsonResponse
    {
        $ticket = $this->findTicket($ticketId);
        $this->authorize('create', [TicketImage::class, $ticket]);

        $request->validate([
            'image' => ['required', 'file', PrivateFile::IMAGE_MIMES, 'max:'.PrivateFile::IMAGE_MAX_KB],
            'description' => ['sometimes', 'string', 'max:255'],
        ]);

        $path = $request->file('image')->store('tickets/'.$ticket->id.'/images', PrivateFile::DISK);

        $image = TicketImage::query()->create([
            'ticket_id' => $ticket->id,
            'uploaded_by' => $request->user()->id,
            'image_path' => $path,
            'description' => $request->input('description'),
        ]);

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

    public function destroy(string $ticketId, string $imageId): JsonResponse
    {
        $image = TicketImage::query()->with('ticket.property')->where('ticket_id', $ticketId)->find($imageId);
        if ($image === null) {
            abort(404);
        }
        $this->authorize('delete', $image);

        PrivateFile::delete($image->image_path);
        $image->delete();

        return response()->json(['message' => 'Image deleted successfully.']);
    }
}
