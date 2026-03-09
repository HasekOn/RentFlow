<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'image' => ['required', 'file', 'image', 'max:5120'],
            'description' => ['sometimes', 'string', 'max:255'],
        ]);

        $path = $request->file('image')->store(
            'tickets/' . $ticket->id . '/images',
            'public'
        );

        $image = TicketImage::query()->create([
            'ticket_id' => $ticket->id,
            'uploaded_by' => $request->user()->id,
            'image_path' => $path,
            'description' => $request->input('description'),
        ]);

        $image->load('uploader:id,name');

        return response()->json($image, 201);
    }

    public function destroy(Request $request, string $ticketId, string $imageId): JsonResponse
    {
        $image = TicketImage::query()
            ->where('ticket_id', $ticketId)
            ->findOrFail($imageId);

        // Only uploader or landlord can delete
        if ($image->uploaded_by !== $request->user()->id && $request->user()->role !== 'landlord') {
            return response()->json(['message' => 'You can only delete your own images.'], 403);
        }

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        return response()->json(['message' => 'Image deleted successfully.']);
    }
}
