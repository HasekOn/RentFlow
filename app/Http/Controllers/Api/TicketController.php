<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Events\TicketResolved;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Lease;
use App\Models\Ticket;
use App\Models\User;
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

        if ($user->isLandlord()) {
            $query = Ticket::query()->whereIn(
                'property_id',
                $user->ownedProperties()->pluck('id')
            )->with(['property', 'tenant', 'assignedUser']);
        } elseif ($user->isManager()) {
            $managedPropertyIds = $user->managedProperties()->pluck('properties.id');

            $query = Ticket::query()
                ->where(function ($q) use ($user, $managedPropertyIds) {
                    $q->whereIn('property_id', $managedPropertyIds)
                        ->orWhere('assigned_to', $user->id);
                })
                ->with(['property', 'tenant', 'assignedUser']);
        } else {
            // Tenant: sees own tickets + all tickets on properties where they have an active lease
            $tenantPropertyIds = Lease::query()
                ->where('tenant_id', $user->id)
                ->where('status', LeaseStatus::Active)
                ->pluck('property_id');

            $query = Ticket::query()
                ->where(function ($q) use ($user, $tenantPropertyIds) {
                    $q->where('tenant_id', $user->id)
                        ->orWhereIn('property_id', $tenantPropertyIds);
                })
                ->with(['property', 'tenant', 'assignedUser']);
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
        $user = $request->user();

        // Manager creates ticket on behalf — set tenant_id to null or self
        $validated['tenant_id'] = $user->id;

        // Verify manager has access to this property
        if ($user->isManager()) {
            $hasAccess = $user->managedProperties()
                ->where('properties.id', $validated['property_id'])
                ->exists();

            if (! $hasAccess) {
                return response()->json([
                    'message' => 'You can only create tickets for your managed properties.',
                ], 403);
            }
        }

        $ticket = Ticket::query()->create($validated);
        $ticket->load(['property', 'tenant']);

        TicketCreated::dispatch($ticket);

        return response()->json(new TicketResource($ticket), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $ticket = Ticket::query()->with([
            'property',
            'tenant',
            'assignedUser',
            'comments.user',
            'images.uploader',
        ])->findOrFail($id);

        $this->authorize('view', $ticket);

        return response()->json(new TicketResource($ticket));
    }

    public function update(UpdateTicketRequest $request, string $id): JsonResponse
    {
        $ticket = Ticket::query()->findOrFail($id);

        $this->authorize('update', $ticket);

        $validated = $request->validated();

        $resolved = ($validated['status'] ?? null) === TicketStatus::Resolved->value;

        // Auto-set resolved_at when status changes to resolved
        if ($resolved) {
            $validated['resolved_at'] = now();
        }

        $ticket->update($validated);

        // Notify tenant when ticket is resolved
        if ($resolved) {
            TicketResolved::dispatch($ticket);
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
