<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        $expenses = Expense::whereIn(
            'property_id',
            $request->user()->ownedProperties()->pluck('id')
        )->with('property')->get();

        return response()->json($expenses);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'type' => ['required', 'in:repair,insurance,tax,maintenance,other'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'invoice_path' => ['nullable', 'string'],
        ]);

        // Verify property belongs to landlord
        $property = Property::findOrFail($validated['property_id']);

        if ($property->landlord_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You can only add expenses to your own properties.',
            ], 403);
        }

        $expense = Expense::create($validated);

        return response()->json($expense, 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('view', Expense::class);

        $expense = Expense::with('property')->findOrFail($id);

        return response()->json($expense);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);

        $this->authorize('update', $expense);

        $validated = $request->validate([
            'type' => ['sometimes', 'in:repair,insurance,tax,maintenance,other'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'expense_date' => ['sometimes', 'date'],
            'description' => ['nullable', 'string'],
            'invoice_path' => ['nullable', 'string'],
        ]);

        $expense->update($validated);

        return response()->json($expense);
    }

    public function destroy(string $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);

        $this->authorize('delete', $expense);

        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully.',
        ]);
    }
}
