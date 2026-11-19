<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FindsTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketCommentRequest;
use App\Http\Resources\TicketCommentResource;
use App\Models\TicketComment;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketCommentController extends Controller
{
    use FindsTicket;

    public function index(string $ticketId): JsonResponse
    {
        $ticket = $this->findTicket($ticketId);
        $this->authorize('viewAny', [TicketComment::class, $ticket]);

        $comments = $ticket->comments()
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return response()->json(TicketCommentResource::collection($comments));
    }

    public function store(StoreTicketCommentRequest $request, string $ticketId): JsonResponse
    {
        $ticket = $this->findTicket($ticketId);
        $this->authorize('create', [TicketComment::class, $ticket]);

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

    public function destroy(string $ticketId, string $commentId): JsonResponse
    {
        $comment = TicketComment::query()->where('ticket_id', $ticketId)->find($commentId);
        if ($comment === null) {
            abort(404);
        }
        $this->authorize('delete', $comment);

        PrivateFile::delete($comment->attachment_path);
        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully.',
        ]);
    }
}
