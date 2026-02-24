<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Property;
use App\Traits\Filterable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use Filterable;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Expense::class);

        $query = Expense::query()->whereIn(
            'property_id',
            $request->user()->ownedProperties()->pluck('id')
        )->with('property');

        $this->applyFilters(
            $query,
            $request,
            filterableFields: ['type', 'property_id'],
            sortableFields: ['expense_date', 'amount', 'type', 'created_at'],
            searchableFields: ['description'],
        );

        return ExpenseResource::collection($query->paginate(20));
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $this->authorize('create', Expense::class);

        // Verify property belongs to landlord
        $property = Property::query()->findOrFail($request->validated('property_id'));

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only add expenses to your own properties.',
            ], 403);
        }

        $expense = Expense::query()->create($request->validated());

        return response()->json(new ExpenseResource($expense), 201);
    }

    public function show(string $id): JsonResponse
    {
        $expense = Expense::query()->with('property')->findOrFail($id);

        $this->authorize('view', $expense);

        return response()->json(new ExpenseResource($expense));
    }

    public function update(UpdateExpenseRequest $request, string $id): JsonResponse
    {
        $expense = Expense::query()->findOrFail($id);

        $this->authorize('update', $expense);

        $expense->update($request->validated());

        return response()->json(new ExpenseResource($expense));
    }

    public function destroy(string $id): JsonResponse
    {
        $expense = Expense::query()->findOrFail($id);

        $this->authorize('delete', $expense);

        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully.',
        ]);
    }

    protected function getDateField(): string
    {
        return 'expense_date';
    }
}
