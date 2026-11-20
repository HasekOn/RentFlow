<?php

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Models\Property;

/**
 * Založení nákladu nemovitosti nebo jeho úprava. Autorizaci (vlastník nemovitosti) dělá volající.
 */
final class SaveExpense
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole
     */
    public function handle(Property $property, array $data, ?Expense $expense = null): Expense
    {
        $expense ??= new Expense(['property_id' => $property->id]);
        $expense->fill($data)->save();

        return $expense;
    }
}
