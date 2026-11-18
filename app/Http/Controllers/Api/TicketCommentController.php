<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketCommentRequest;
use App\Http\Resources\TicketCommentResource;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $attachmentPath = null;

        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('tickets/'.$ticket->id, PrivateFile::DISK);
        }

        $comment = TicketComment::query()->create([
            'message' => $request->validated('message'),
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'attachment_path' => $attachmentPath,
        ]);

        $comment->load('user');

        return response()->json(new TicketCommentResource($comment), 201);
    }

    public function attachment(string $ticketId, string $commentId): StreamedResponse
    {
        $comment = TicketComment::query()->with('ticket.property')->where('ticket_id', $ticketId)->find($commentId);
        if ($comment === null || $comment->ticket === null || $comment->attachment_path === null) {
            abort(404);
        }

        $this->authorize('view', $comment->ticket);

        return PrivateFile::download($comment->attachment_path, basename($comment->attachment_path));
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

        PrivateFile::delete($comment->attachment_path);
        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully.',
        ]);
    }
}
