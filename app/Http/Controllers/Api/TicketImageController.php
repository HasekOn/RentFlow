<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketImage;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketImageController extends Controller
{
    public function index(string $ticketId): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($ticketId);

        $images = $ticket->images()
            ->with('uploader:id,name')
            ->orderBy('created_at')
            ->get();

        return response()->json($images);
    }

    public function store(Request $request, string $ticketId): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($ticketId);

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

        $image->load('uploader:id,name');

        return response()->json($image, 201);
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

    public function destroy(Request $request, string $ticketId, string $imageId): JsonResponse
    {
        $image = TicketImage::query()
            ->where('ticket_id', $ticketId)
            ->findOrFail($imageId);

        // Only uploader or landlord can delete
        if ($image->uploaded_by !== $request->user()->id && ! $request->user()->isLandlord()) {
            return response()->json(['message' => 'You can only delete your own images.'], 403);
        }

        PrivateFile::delete($image->image_path);
        $image->delete();

        return response()->json(['message' => 'Image deleted successfully.']);
    }
}
