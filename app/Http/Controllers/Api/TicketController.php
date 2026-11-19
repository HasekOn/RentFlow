<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Events\TicketResolved;
use App\Http\Controllers\Api\Concerns\FindsProperty;
use App\Http\Controllers\Api\Concerns\FindsTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Traits\Filterable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    use Filterable, FindsProperty, FindsTicket;

    /** Pole, která smí autor upravit ve stavu new (#46); ostatní jen ten, kdo tiket řeší. */
    private const array DETAIL_FIELDS = ['title', 'description'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', Ticket::class);

        /** @var User $user */
        $user = $request->user();

        $query = Ticket::query()
            ->visibleTo($user)
            ->with(['property', 'tenant', 'assignedUser']);

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
        $property = $this->findProperty((string) $request->validated('property_id'));
        $this->authorize('create', [Ticket::class, $property]);

        $ticket = Ticket::query()->create([...$request->validated(), 'tenant_id' => $request->user()->id]);
        $ticket->load(['property', 'tenant']);

        TicketCreated::dispatch($ticket);

        return response()->json(new TicketResource($ticket), 201);
    }

    public function show(string $id): JsonResponse
    {
        $ticket = Ticket::query()->with([
            'property',
            'tenant',
            'assignedUser',
            'comments.user',
            'images.uploader',
        ])->find($id);
        if ($ticket === null) {
            abort(404);
        }
        $this->authorize('view', $ticket);

        return response()->json(new TicketResource($ticket));
    }

    public function update(UpdateTicketRequest $request, string $id): JsonResponse
    {
        $ticket = $this->findTicket($id);
        $validated = $request->validated();

        $onlyDetails = array_diff(array_keys($validated), self::DETAIL_FIELDS) === [];
        $this->authorize($onlyDetails ? 'updateDetails' : 'update', $ticket);

        // řešitel jen správce přiřazený k nemovitosti tiketu (#23); až po autorizaci, ať 422 cizímu nic neprozradí
        $assignee = $validated['assigned_to'] ?? null;
        if ($assignee !== null && ! $ticket->property?->managers()->whereKey($assignee)->exists()) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Assignee must be a manager assigned to the ticket property.',
            ]);
        }

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
        $ticket = $this->findTicket($id);
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
