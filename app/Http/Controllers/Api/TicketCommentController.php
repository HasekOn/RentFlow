<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketCommentController extends Controller
{
    public function index(string $ticketId): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($ticketId);

        $comments = $ticket->comments()
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return response()->json($comments);
    }

    public function store(Request $request, string $ticketId): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($ticketId);

        $validated = $request->validate([
            'message' => ['required', 'string'],
            'attachment_path' => ['nullable', 'string'],
        ]);

        $validated['ticket_id'] = $ticket->id;
        $validated['user_id'] = $request->user()->id;

        $comment = TicketComment::create($validated);
        $comment->load('user');

        return response()->json($comment, 201);
    }

    public function destroy(Request $request, string $ticketId, string $commentId): JsonResponse
    {
        $comment = TicketComment::query()->where('ticket_id', $ticketId)
            ->findOrFail($commentId);

        // Only the author can delete their comment
        if ($comment->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only delete your own comments.',
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully.',
        ]);
    }
}
