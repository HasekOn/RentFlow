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
use App\Traits\Filterable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    use Filterable;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Ticket::class);

        /** @var User $user */
        $user = $request->user();

        if ($user->role === 'landlord') {
            $query = Ticket::query()->whereIn(
                'property_id',
                $user->ownedProperties()->pluck('id')
            )->with(['property', 'tenant', 'assignedUser']);
        } elseif ($user->role === 'manager') {
            $query = $user->assignedTickets()
                ->with(['property', 'tenant'])
                ->getQuery();
        } else {
            $query = $user->tickets()
                ->with('property')
                ->getQuery();
        }

        $this->applyFilters(
            $query,
            $request,
            filterableFields: ['status', 'priority', 'category', 'property_id'],
            sortableFields: ['created_at', 'priority', 'status', 'resolved_at'],
            searchableFields: ['title', 'description'],
        );

        return TicketResource::collection($query->paginate(15));
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

    protected function getDateField(): string
    {
        return 'created_at';
    }
}
