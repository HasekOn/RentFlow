<?php

namespace App\Http\Controllers\Api;

use App\Actions\Expenses\DeleteExpense;
use App\Actions\Expenses\SaveExpense;
use App\Http\Controllers\Api\Concerns\FindsProperty;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Traits\Filterable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    use Filterable, FindsProperty;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Expense::class);

        $query = Expense::query()
            ->visibleTo($request->user())
            ->with('property');

        $this->applyFilters(
            $query,
            $request,
            filterableFields: ['type', 'property_id'],
            sortableFields: ['expense_date', 'amount', 'type', 'created_at'],
            searchableFields: ['description'],
        );

        return ExpenseResource::collection($query->paginate(20));
    }

    public function store(StoreExpenseRequest $request, SaveExpense $save): JsonResponse
    {
        $property = $this->findProperty((string) $request->validated('property_id'));
        $this->authorize('create', [Expense::class, $property]);

        $expense = $save->handle($property, $request->validated());

        return response()->json(new ExpenseResource($expense), 201);
    }

    public function show(string $id): JsonResponse
    {
        $expense = $this->findExpense($id);
        $this->authorize('view', $expense);

        return response()->json(new ExpenseResource($expense));
    }

    public function update(UpdateExpenseRequest $request, string $id, SaveExpense $save): JsonResponse
    {
        $expense = $this->findExpense($id);
        $this->authorize('update', $expense);

        $save->handle($expense->property, $request->validated(), $expense);

        return response()->json(new ExpenseResource($expense));
    }

    public function destroy(string $id, DeleteExpense $delete): JsonResponse
    {
        $expense = $this->findExpense($id);
        $this->authorize('delete', $expense);

        $delete->handle($expense);

        return response()->json([
            'message' => 'Expense deleted successfully.',
        ]);
    }

    private function findExpense(string $id): Expense
    {
        $expense = Expense::query()->with('property')->find($id);
        if ($expense === null) {
            abort(404);
        }

        return $expense;
    }

    protected function getDateField(): string
    {
        return 'expense_date';
    }
}
