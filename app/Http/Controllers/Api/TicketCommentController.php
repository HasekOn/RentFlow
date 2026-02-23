<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketCommentRequest;
use App\Http\Resources\TicketCommentResource;
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

        return response()->json(TicketCommentResource::collection($comments));
    }

    public function store(StoreTicketCommentRequest $request, string $ticketId): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($ticketId);

        $comment = TicketComment::query()->create([
            ...$request->validated(),
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
        ]);

        $comment->load('user');

        return response()->json(new TicketCommentResource($comment), 201);
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
