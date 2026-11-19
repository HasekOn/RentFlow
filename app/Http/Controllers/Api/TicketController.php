<?php

namespace App\Http\Controllers\Api;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\DeleteTicket;
use App\Actions\Tickets\UpdateTicket;
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

    public function store(StoreTicketRequest $request, CreateTicket $create): JsonResponse
    {
        $property = $this->findProperty((string) $request->validated('property_id'));
        $this->authorize('create', [Ticket::class, $property]);

        $ticket = $create->handle($property, $request->user(), $request->validated());
        $ticket->load('tenant');

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

    public function update(UpdateTicketRequest $request, string $id, UpdateTicket $update): JsonResponse
    {
        $ticket = $this->findTicket($id);
        $validated = $request->validated();

        $onlyDetails = array_diff(array_keys($validated), self::DETAIL_FIELDS) === [];
        $this->authorize($onlyDetails ? 'updateDetails' : 'update', $ticket);

        // kontrola řešitele až po autorizaci, ať 422 cizímu nic neprozradí
        $update->handle($ticket, $validated);

        return response()->json(new TicketResource($ticket));
    }

    public function destroy(string $id, DeleteTicket $delete): JsonResponse
    {
        $ticket = $this->findTicket($id);
        $this->authorize('delete', $ticket);

        $delete->handle($ticket);

        return response()->json([
            'message' => 'Ticket deleted successfully.',
        ]);
    }
}
