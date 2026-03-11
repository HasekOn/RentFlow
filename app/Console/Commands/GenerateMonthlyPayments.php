<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Models\Payment;
use Illuminate\Console\Command;

class GenerateMonthlyPayments extends Command
{
    protected $signature = 'rentflow:generate-payments';

    protected $description = 'Generate monthly rent and utility payments for active leases';

    public function handle(): int
    {
        $activeLeases = Lease::query()
            ->where('status', 'active')
            ->with('property')
            ->get();

        $created = 0;
        $dueDate = now()->startOfMonth()->addDays(14);

        foreach ($activeLeases as $lease) {
            // Rent
            $existsRent = Payment::query()
                ->where('lease_id', $lease->id)
                ->where('type', 'rent')
                ->whereYear('due_date', now()->year)
                ->whereMonth('due_date', now()->month)
                ->exists();

            if (! $existsRent) {
                Payment::create([
                    'lease_id' => $lease->id,
                    'type' => 'rent',
                    'amount' => $lease->rent_amount,
                    'due_date' => $dueDate,
                    'variable_symbol' => $lease->variable_symbol,
                    'status' => 'unpaid',
                ]);
                $created++;
                $this->info("Rent for lease #{$lease->id} ({$lease->property?->address})");
            }

            // Utilities
            if ($lease->utility_advances > 0) {
                $existsUtility = Payment::query()
                    ->where('lease_id', $lease->id)
                    ->where('type', 'utilities')
                    ->whereYear('due_date', now()->year)
                    ->whereMonth('due_date', now()->month)
                    ->exists();

                if (! $existsUtility) {
                    Payment::create([
                        'lease_id' => $lease->id,
                        'type' => 'utilities',
                        'amount' => $lease->utility_advances,
                        'due_date' => $dueDate,
                        'variable_symbol' => $lease->variable_symbol ? $lease->variable_symbol.'1' : null,
                        'status' => 'unpaid',
                    ]);
                    $created++;
                }
            }
        }

        $this->info("Total payments generated: {$created}");

        return self::SUCCESS;
    }
}
