<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\Payment;

class BankImportService
{
    /**
     * Parse CSV and match payments by variable symbol
     * Returns summary of matched, unmatched and already paid
     */
    public function import(string $csvContent, int $landlordId): array
    {
        $rows = $this->parseCsv($csvContent);
        $results = [
            'matched' => [],
            'already_paid' => [],
            'unmatched' => [],
            'total_rows' => count($rows),
        ];

        // Get all leases for this landlord's properties, keyed by cleaned VS
        $landlordLeases = Lease::query()->whereHas('property', function ($query) use ($landlordId) {
            $query->where('landlord_id', $landlordId);
        })->whereNotNull('variable_symbol')
            ->get(['id', 'variable_symbol']);

        // Build lookup: cleaned VS → lease ID (handles leading zeros consistently)
        $vsToLeaseId = [];
        foreach ($landlordLeases as $lease) {
            $cleanedVs = $this->cleanVariableSymbol($lease->variable_symbol);
            if ($cleanedVs !== '') {
                $vsToLeaseId[$cleanedVs] = $lease->id;
            }
        }

        // Track which payment IDs we've already matched in THIS import
        // to prevent a single CSV from matching multiple payments accidentally
        $matchedPaymentIds = [];

        foreach ($rows as $row) {
            $vs = $this->cleanVariableSymbol($row['variable_symbol'] ?? '');
            $amount = $this->cleanAmount($row['amount'] ?? '0');
            $date = $this->cleanDate($row['date'] ?? '');

            // Skip rows without variable symbol
            if (empty($vs)) {
                $results['unmatched'][] = [
                    'row' => $row,
                    'reason' => 'Missing variable symbol',
                ];

                continue;
            }

            // Skip rows with zero or negative amount
            if ($amount <= 0) {
                $results['unmatched'][] = [
                    'row' => $row,
                    'reason' => 'Invalid amount: '.($row['amount'] ?? '0'),
                ];

                continue;
            }

            // Find lease by variable symbol
            if (! isset($vsToLeaseId[$vs])) {
                $results['unmatched'][] = [
                    'row' => $row,
                    'reason' => 'Variable symbol not found: '.$vs,
                ];

                continue;
            }

            $leaseId = $vsToLeaseId[$vs];

            // Find unpaid/overdue payment for this lease
            // Match by amount first for accuracy, then by VS on payment if set
            $paymentQuery = Payment::query()
                ->where('lease_id', $leaseId)
                ->whereIn('status', ['unpaid', 'overdue'])
                ->whereNotIn('id', $matchedPaymentIds);

            // Try exact amount match first (most reliable)
            $payment = (clone $paymentQuery)
                ->where('amount', $amount)
                ->orderBy('due_date')
                ->first();

            // If no exact match, try any unpaid payment on the lease
            if (! $payment) {
                $payment = $paymentQuery
                    ->orderBy('due_date')
                    ->first();
            }

            if (! $payment) {
                // Check if there's a paid payment with same VS and date to detect duplicate import
                $alreadyImported = Payment::query()
                    ->where('lease_id', $leaseId)
                    ->where('status', 'paid')
                    ->where('note', 'LIKE', '%CSV import%')
                    ->when($date, fn ($q) => $q->where('paid_date', $date))
                    ->where('amount', $amount)
                    ->exists();

                $reason = $alreadyImported
                    ? 'Already imported (duplicate CSV row) for VS: '.$vs
                    : 'No unpaid payment found for VS: '.$vs;

                $results['already_paid'][] = [
                    'row' => $row,
                    'reason' => $reason,
                ];

                continue;
            }

            // Track this payment to prevent double-matching within same import
            $matchedPaymentIds[] = $payment->id;

            // Check amount mismatch — still match but add warning
            $amountMismatch = abs((float) $payment->amount - $amount) > 0.01;

            // Match — mark as paid
            $payment->update([
                'paid_date' => $date ?: now()->toDateString(),
                'status' => 'paid',
                'variable_symbol' => $vs,
                'note' => 'Auto-matched from bank CSV import'.($amountMismatch
                        ? ' (CSV amount: '.number_format($amount, 2).', expected: '.number_format((float) $payment->amount, 2).')'
                        : ''),
            ]);

            // Recalculate trust score
            $payment->load('lease.tenant');
            $tenant = $payment->lease?->tenant;
            $tenant?->recalculateTrustScore();

            $results['matched'][] = [
                'payment_id' => $payment->id,
                'lease_id' => $leaseId,
                'variable_symbol' => $vs,
                'amount' => $amount,
                'payment_amount' => (float) $payment->amount,
                'amount_mismatch' => $amountMismatch,
                'date' => $date,
            ];
        }

        return $results;
    }

    /**
     * Parse CSV string into array of rows
     * Supports common Czech bank export formats
     */
    private function parseCsv(string $content): array
    {
        $rows = [];

        // Remove BOM if present
        $content = str_replace("\xEF\xBB\xBF", '', $content);

        $lines = array_filter(explode("\n", trim($content)));

        if (count($lines) < 2) {
            return [];
        }

        // Detect delimiter (semicolon is common in Czech bank exports)
        $firstLine = $lines[0];
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';

        // Parse header
        $header = str_getcsv(array_shift($lines), $delimiter);
        $header = array_map(fn ($h) => $this->normalizeHeader(trim($h)), $header);

        // Parse data rows
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $values = str_getcsv($line, $delimiter);

            if (count($values) !== count($header)) {
                continue; // Skip malformed rows
            }

            $row = array_combine($header, $values);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Normalize CSV header names to standard keys
     * Maps common Czech bank column names to our expected format
     */
    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));

        // Remove BOM and quotes
        $header = str_replace(["\xEF\xBB\xBF", '"', "'"], '', $header);

        $map = [
            // Variable symbol variants
            'vs' => 'variable_symbol',
            'variabilní symbol' => 'variable_symbol',
            'variabilni symbol' => 'variable_symbol',
            'variable symbol' => 'variable_symbol',
            'variable_symbol' => 'variable_symbol',
            'var. symbol' => 'variable_symbol',
            'var.symbol' => 'variable_symbol',

            // Amount variants
            'částka' => 'amount',
            'castka' => 'amount',
            'amount' => 'amount',
            'objem' => 'amount',
            'suma' => 'amount',

            // Date variants
            'datum' => 'date',
            'date' => 'date',
            'datum zaúčtování' => 'date',
            'datum zauctovani' => 'date',
            'datum platby' => 'date',

            // Note variants
            'note' => 'note',
            'poznámka' => 'note',
            'poznamka' => 'note',
            'zpráva pro příjemce' => 'note',
            'zprava pro prijemce' => 'note',
            'message' => 'note',

            // Counter account info (for reference)
            'protiúčet' => 'counter_account',
            'protiucet' => 'counter_account',
            'název protiúčtu' => 'counter_name',
        ];

        return $map[$header] ?? $header;
    }

    /**
     * Clean variable symbol — remove leading zeros and whitespace
     */
    private function cleanVariableSymbol(string $vs): string
    {
        $vs = trim($vs);
        // Remove all non-numeric characters
        $vs = preg_replace('/[^0-9]/', '', $vs);

        return ltrim($vs, '0');
    }

    /**
     * Clean amount — handle Czech number format (1 234,56)
     */
    private function cleanAmount(string $amount): float
    {
        $amount = trim($amount);
        $amount = str_replace(' ', '', $amount);    // Remove spaces (thousand separator)
        $amount = str_replace(',', '.', $amount);    // Czech decimal comma to dot

        return abs((float) $amount); // Always positive (some exports use negative for outgoing)
    }

    /**
     * Clean date — handle common Czech formats
     */
    private function cleanDate(string $date): ?string
    {
        $date = trim($date);

        if (empty($date)) {
            return null;
        }

        // Try dd.mm.yyyy (Czech format)
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $date, $m)) {
            return $m[3].'-'.str_pad($m[2], 2, '0', STR_PAD_LEFT).'-'.str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        // Try yyyy-mm-dd (ISO format)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // Try dd/mm/yyyy
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $m)) {
            return $m[3].'-'.str_pad($m[2], 2, '0', STR_PAD_LEFT).'-'.str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        return null;
    }
}
