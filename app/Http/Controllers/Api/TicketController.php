<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketResolvedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        /** @var User $user */
        $user = $request->user();

        if ($user->role === 'landlord') {
            $tickets = Ticket::query()->whereIn(
                'property_id',
                $user->ownedProperties()->pluck('id')
            )->with(['property', 'tenant', 'assignedUser'])->get();
        } elseif ($user->role === 'manager') {
            $tickets = $user->assignedTickets()
                ->with(['property', 'tenant'])
                ->get();
        } else {
            $tickets = $user->tickets()
                ->with('property')
                ->get();
        }

        return response()->json(TicketResource::collection($tickets));
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $validated = $request->validated();

        $validated['tenant_id'] = $request->user()->id;

        $ticket = Ticket::query()->create($validated);

        $ticket->load(['property', 'tenant']);

        // Notify landlord about new ticket
        $landlord = $ticket->property->landlord;

        if ($landlord) {
            $landlord->notify(new TicketCreatedNotification($ticket));
        }

        return response()->json(new TicketResource($ticket), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $ticket = Ticket::query()->with([
            'property',
            'tenant',
            'assignedUser',
            'comments.user',
        ])->findOrFail($id);

        $this->authorize('view', $ticket);

        return response()->json(new TicketResource($ticket));
    }

    public function update(UpdateTicketRequest $request, string $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);

        $this->authorize('update', $ticket);

        $validated = $request->validated();

        // Auto-set resolved_at when status changes to resolved
        if (isset($validated['status']) && $validated['status'] === 'resolved') {
            $validated['resolved_at'] = now();
        }

        $ticket->update($validated);

        // Notify tenant when ticket is resolved
        if (isset($validated['status']) && $validated['status'] === 'resolved') {
            $ticket->load('tenant');

            if ($ticket->tenant) {
                $ticket->tenant->notify(new TicketResolvedNotification($ticket));
            }
        }

        return response()->json(new TicketResource($ticket));
    }

    public function destroy(string $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);

        $this->authorize('delete', $ticket);

        $ticket->delete();

        return response()->json([
            'message' => 'Ticket deleted successfully.',
        ]);
    }
}
