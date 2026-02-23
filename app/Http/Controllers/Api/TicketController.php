<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Ticket;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketResolvedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $user = $request->user();

        if ($user->role === 'landlord') {
            // Landlord sees all tickets for their properties
            $tickets = Ticket::query()->whereIn(
                'property_id',
                $user->ownedProperties()->pluck('id')
            )->with(['property', 'tenant', 'assignedUser'])->get();
        } elseif ($user->role === 'manager') {
            // Manager sees tickets assigned to them
            $tickets = $user->assignedTickets()
                ->with(['property', 'tenant'])
                ->get();
        } else {
            // Tenant sees only their own tickets
            $tickets = $user->tickets()
                ->with('property')
                ->get();
        }

        return response()->json($tickets);
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

        return response()->json($ticket, 201);
    }

    public function show(string $id): JsonResponse
    {
        $ticket = Ticket::with([
            'property',
            'tenant',
            'assignedUser',
            'comments.user',
        ])->findOrFail($id);

        $this->authorize('view', $ticket);

        return response()->json($ticket);
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

        return response()->json($ticket);
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
