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

        // Get all lease IDs for this landlord's properties
        $landlordLeaseIds = Lease::query()->whereHas('property', function ($query) use ($landlordId) {
            $query->where('landlord_id', $landlordId);
        })->pluck('id', 'variable_symbol')->toArray();
        // Result: ['VS123' => 5, 'VS456' => 8, ...]

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

            // Find lease by variable symbol
            if (!isset($landlordLeaseIds[$vs])) {
                $results['unmatched'][] = [
                    'row' => $row,
                    'reason' => 'Variable symbol not found: ' . $vs,
                ];
                continue;
            }

            $leaseId = $landlordLeaseIds[$vs];

            // Find unpaid payment for this lease closest to the date
            $payment = Payment::query()->where('lease_id', $leaseId)
                ->where('status', 'unpaid')
                ->where('variable_symbol', $vs)
                ->orderBy('due_date')
                ->first();

            if (!$payment) {
                // Try without variable_symbol on payment (might not be set)
                $payment = Payment::query()->where('lease_id', $leaseId)
                    ->where('status', 'unpaid')
                    ->orderBy('due_date')
                    ->first();
            }

            if (!$payment) {
                $results['already_paid'][] = [
                    'row' => $row,
                    'reason' => 'No unpaid payment found for VS: ' . $vs,
                ];
                continue;
            }

            // Match — mark as paid
            $payment->update([
                'paid_date' => $date ?: now()->toDateString(),
                'status' => 'paid',
                'variable_symbol' => $vs,
                'note' => 'Auto-matched from bank CSV import',
            ]);

            // Recalculate trust score
            $payment->load('lease.tenant');
            $tenant = $payment->lease?->tenant;
            if ($tenant) {
                $tenant->recalculateTrustScore();
            }

            $results['matched'][] = [
                'payment_id' => $payment->id,
                'lease_id' => $leaseId,
                'variable_symbol' => $vs,
                'amount' => $amount,
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
        $lines = array_filter(explode("\n", trim($content)));

        if (count($lines) < 2) {
            return [];
        }

        // Detect delimiter (semicolon is common in Czech bank exports)
        $firstLine = $lines[0];
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';

        // Parse header
        $header = str_getcsv(array_shift($lines), $delimiter);
        $header = array_map(fn($h) => $this->normalizeHeader(trim($h)), $header);

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
        $header = mb_strtolower($header);

        // Remove BOM and quotes
        $header = str_replace(["\xEF\xBB\xBF", '"', "'"], '', $header);

        $map = [
            // Variable symbol variants
            'vs' => 'variable_symbol',
            'variabilní symbol' => 'variable_symbol',
            'variabilni symbol' => 'variable_symbol',
            'variable symbol' => 'variable_symbol',
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

            // Counter account info (for reference)
            'protiúčet' => 'counter_account',
            'protiucet' => 'counter_account',
            'název protiúčtu' => 'counter_name',
            'zpráva pro příjemce' => 'message',
            'zprava pro prijemce' => 'message',
        ];

        return $map[$header] ?? $header;
    }

    /**
     * Clean variable symbol — remove leading zeros and whitespace
     */
    private function cleanVariableSymbol(string $vs): string
    {
        $vs = trim($vs);

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
        return (float)$amount;
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
            return $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        // Try yyyy-mm-dd (ISO format)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // Try dd/mm/yyyy
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $m)) {
            return $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        return null;
    }
}
