<?php

namespace App\Actions\Payments;

use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Měsíční nájem a zálohy na služby pro aktivní smlouvy. Jedna logika pro příkaz scheduleru i API.
 *
 * Idempotentní: platba stejného typu, která už v daném měsíci u smlouvy je, se znovu nevytvoří. Zámek
 * v cache (store database funguje napříč procesy) brání souběhu scheduleru a tlačítka v API.
 */
final class GenerateMonthlyPayments
{
    private const string LOCK = 'payments:generate-monthly';

    /**
     * @param  User|null  $landlord  jen smlouvy jeho nemovitostí, null = všechny (příkaz scheduleru)
     * @return int počet vytvořených plateb
     */
    public function handle(?User $landlord, CarbonInterface $month): int
    {
        return Cache::lock(self::LOCK, 60)->block(10, fn (): int => DB::transaction(
            fn (): int => $this->generate($landlord, $month->copy()->startOfMonth()),
        ));
    }

    private function generate(?User $landlord, CarbonInterface $monthStart): int
    {
        $leases = Lease::query()
            ->where('status', LeaseStatus::Active)
            ->when($landlord !== null, fn ($query) => $query->whereHas(
                'property',
                fn ($property) => $property->where('landlord_id', $landlord?->id),
            ))
            ->get();

        // existující platby měsíce jedním dotazem (rozsah >= a <, SQLite ukládá datum s časem)
        $existing = Payment::query()
            ->whereIn('lease_id', $leases->modelKeys())
            ->whereIn('type', [PaymentType::Rent, PaymentType::Utilities])
            ->where('due_date', '>=', $monthStart)
            ->where('due_date', '<', $monthStart->copy()->addMonth())
            ->get(['lease_id', 'type'])
            ->mapWithKeys(fn (Payment $payment) => [$payment->lease_id.':'.$payment->type->value => true]);

        $dueDate = $monthStart->copy()->addDays(14);
        $created = 0;

        foreach ($leases as $lease) {
            $planned = [PaymentType::Rent->value => [$lease->rent_amount, $lease->variable_symbol]];
            if ($lease->utility_advances > 0) {
                $planned[PaymentType::Utilities->value] = [
                    $lease->utility_advances,
                    $lease->variable_symbol ? $lease->variable_symbol.'1' : null,
                ];
            }

            foreach ($planned as $type => [$amount, $variableSymbol]) {
                if ($existing->has($lease->id.':'.$type)) {
                    continue;
                }

                Payment::query()->create([
                    'lease_id' => $lease->id,
                    'type' => $type,
                    'amount' => $amount,
                    'due_date' => $dueDate,
                    'variable_symbol' => $variableSymbol,
                    'status' => PaymentStatus::Unpaid,
                ]);
                $created++;
            }
        }

        return $created;
    }
}
