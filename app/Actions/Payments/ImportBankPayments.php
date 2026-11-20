<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use App\Services\BankImportService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Import bankovního výpisu (CSV) pronajímatele (#37, #48, #49). Řádek se páruje podle VS platby (zálohy mají
 * VS smlouvy + „1“) i VS smlouvy, jen na nezaplacené platby nemovitostí pronajímatele. Zaplacená se označí jen
 * při shodě částky (v haléřích), jinak jde řádek „ke kontrole“ a platba zůstane nezaplacená. Celé v transakci
 * (chyba uprostřed nic neuloží), počet dotazů nezávisí na počtu řádků a trust score se přepočítá jednou
 * na nájemníka. Autorizaci dělá volající.
 */
final class ImportBankPayments
{
    public const string NOTE = 'Auto-matched from bank CSV import';

    public function __construct(private readonly BankImportService $parser) {}

    /**
     * @return array{total_rows: int, matched: list<array<string, mixed>>, to_review: list<array<string, mixed>>, already_paid: list<array<string, mixed>>, unmatched: list<array<string, mixed>>}
     */
    public function handle(User $landlord, string $csv): array
    {
        $rows = $this->parser->parse($csv);

        return DB::transaction(fn (): array => $this->import($landlord, $rows));
    }

    /**
     * @param  list<array{row: array<string, string>, variable_symbol: string, amount: int|null, date: string|null}>  $rows
     * @return array{total_rows: int, matched: list<array<string, mixed>>, to_review: list<array<string, mixed>>, already_paid: list<array<string, mixed>>, unmatched: list<array<string, mixed>>}
     */
    private function import(User $landlord, array $rows): array
    {
        $results = ['total_rows' => count($rows), 'matched' => [], 'to_review' => [], 'already_paid' => [], 'unmatched' => []];

        // smlouvy nesmazaných nemovitostí pronajímatele a jejich nezaplacené platby, každé jedním dotazem
        $leases = Lease::query()
            ->whereHas('property', fn ($property) => $property->where('landlord_id', $landlord->id))
            ->get(['id', 'tenant_id', 'variable_symbol']);
        $leaseIdByVs = $leases
            ->mapWithKeys(fn (Lease $lease) => [$this->vs($lease->variable_symbol) => $lease->id])
            ->forget('');
        $open = Payment::query()
            ->whereIn('lease_id', $leases->modelKeys())
            ->whereIn('status', [PaymentStatus::Unpaid, PaymentStatus::Overdue])
            ->orderBy('due_date')->orderBy('id')
            ->get();
        $knownVs = $leaseIdByVs->keys()->merge(Payment::query()
            ->whereIn('lease_id', $leases->modelKeys())
            ->whereNotNull('variable_symbol')
            ->distinct()
            ->pluck('variable_symbol')
            ->map(fn (string $vs) => $this->vs($vs)))->flip();
        $imported = $this->importedBefore($leases->modelKeys(), $rows);

        $matchedIds = [];
        $tenantIds = [];

        foreach ($rows as $parsed) {
            ['row' => $row, 'variable_symbol' => $vs, 'amount' => $amount, 'date' => $date] = $parsed;

            if ($vs === '') {
                $results['unmatched'][] = ['row' => $row, 'reason' => 'Missing variable symbol'];

                continue;
            }
            if ($amount === null || $amount <= 0) {
                $results['unmatched'][] = ['row' => $row, 'reason' => 'Invalid amount: '.($row['amount'] ?? '')];

                continue;
            }
            if (! $knownVs->has($vs)) {
                $results['unmatched'][] = ['row' => $row, 'reason' => 'Variable symbol not found: '.$vs];

                continue;
            }

            $leaseId = $leaseIdByVs->get($vs);
            $candidates = $open
                ->filter(fn (Payment $payment) => ! isset($matchedIds[$payment->id])
                    && ($this->vs($payment->variable_symbol) === $vs || $payment->lease_id === $leaseId))
                ->values();

            if ($candidates->isEmpty()) {
                $duplicate = $imported->contains(fn (Payment $payment) => $this->vs($payment->variable_symbol) === $vs
                    && Money::cents($payment->amount) === $amount
                    && $payment->paid_date?->toDateString() === $date);
                $results['already_paid'][] = [
                    'row' => $row,
                    'reason' => ($duplicate ? 'Already imported (duplicate CSV row) for VS: ' : 'No unpaid payment found for VS: ').$vs,
                ];

                continue;
            }

            $payment = $candidates->first(fn (Payment $payment) => Money::cents($payment->amount) === $amount);
            if ($payment === null) {
                $expected = $candidates->first();
                $results['to_review'][] = [
                    'row' => $row,
                    'payment_id' => $expected->id,
                    'reason' => 'Amount does not match: '.Money::fromCents($amount).', expected: '.Money::fromCents(Money::cents($expected->amount)),
                ];

                continue;
            }

            $matchedIds[$payment->id] = true;
            $payment->update([
                'paid_date' => $date !== null ? Carbon::parse($date) : today(),
                'status' => PaymentStatus::Paid,
                'variable_symbol' => $payment->variable_symbol ?? $vs,
                'note' => self::NOTE,
            ]);
            $tenantIds[] = $leases->find($payment->lease_id)?->tenant_id;

            $results['matched'][] = [
                'payment_id' => $payment->id,
                'lease_id' => $payment->lease_id,
                'variable_symbol' => $vs,
                'amount' => Money::toFloat($amount),
                'date' => $date,
            ];
        }

        User::query()->whereKey(array_unique(array_filter($tenantIds)))->get()
            ->each(fn (User $tenant) => $tenant->recalculateTrustScore());

        return $results;
    }

    /**
     * Platby zaplacené dřívějším importem ve dnech z výpisu (rozpoznání duplicitního řádku).
     *
     * @param  array<int, int|string>  $leaseIds  klíče z modelKeys()
     * @param  list<array{row: array<string, string>, variable_symbol: string, amount: int|null, date: string|null}>  $rows
     * @return Collection<int, Payment>
     */
    private function importedBefore(array $leaseIds, array $rows): Collection
    {
        $dates = array_values(array_unique(array_filter(array_column($rows, 'date'))));
        if ($dates === []) {
            return new Collection;
        }

        return Payment::query()
            ->whereIn('lease_id', $leaseIds)
            ->where('status', PaymentStatus::Paid)
            ->where('note', self::NOTE)
            ->whereIn('paid_date', array_map(fn (string $date) => Carbon::parse($date), $dates))
            ->get();
    }

    private function vs(?string $variableSymbol): string
    {
        return $this->parser->cleanVariableSymbol($variableSymbol ?? '');
    }
}
