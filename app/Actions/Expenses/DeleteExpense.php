<?php

namespace App\Actions\Expenses;

use App\Models\Expense;

/**
 * Smazání nákladu. Autorizaci dělá volající.
 */
final class DeleteExpense
{
    public function handle(Expense $expense): void
    {
        $expense->delete();
    }
}
