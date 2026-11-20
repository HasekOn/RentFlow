<?php

namespace App\Services;

use App\Support\Money;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class BankImportService
{
    /**
     * Řádky bankovního výpisu s očištěnými hodnotami: částka v haléřích (null = nečitelná), datum `Y-m-d`.
     * Párování s platbami dělá Action ImportBankPayments.
     *
     * @return list<array{row: array<string, string>, variable_symbol: string, amount: int|null, date: string|null}>
     *
     * @throws ValidationException soubor není v UTF-8 (např. export banky v cp1250), řádky se vrací v odpovědi
     */
    public function parse(string $csvContent): array
    {
        if (! mb_check_encoding($csvContent, 'UTF-8')) {
            throw ValidationException::withMessages([
                'file' => __('The CSV file must be UTF-8 encoded. Save the bank export as UTF-8 and upload it again.'),
            ]);
        }

        return array_map(fn (array $row): array => [
            'row' => $row,
            'variable_symbol' => $this->cleanVariableSymbol($row['variable_symbol'] ?? ''),
            'amount' => $this->cleanAmount($row['amount'] ?? ''),
            'date' => $this->cleanDate($row['date'] ?? ''),
        ], $this->parseCsv($csvContent));
    }

    /**
     * Parse CSV string into array of rows
     * Supports common Czech bank export formats
     *
     * @return list<array<string, string>>
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
        $header = array_map(fn (?string $h) => $this->normalizeHeader($h ?? ''), $header);

        // Parse data rows
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // prázdná buňka může přijít jako null
            $values = array_map(fn (?string $v) => $v ?? '', str_getcsv($line, $delimiter));

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
     * Clean variable symbol — remove leading zeros and whitespace (stejně se čistí VS smluv a plateb při párování)
     */
    public function cleanVariableSymbol(string $vs): string
    {
        $vs = trim($vs);
        // Remove all non-numeric characters
        $vs = preg_replace('/[^0-9]/', '', $vs) ?? '';

        return ltrim($vs, '0');
    }

    /**
     * Částka v haléřích (#49) z českého formátu („1 234,56“, mezera i nezlomitelná mezera jako oddělovač tisíců).
     * Záporná částka je odchozí platba (zůstane záporná a import ji nespáruje), nečitelná → null.
     */
    private function cleanAmount(string $amount): ?int
    {
        $amount = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($amount));

        try {
            return Money::cents($amount);
        } catch (InvalidArgumentException) {
            return null;
        }
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

        // dd.mm.yyyy (český formát), dd/mm/yyyy a yyyy-mm-dd; neexistující datum (31. 2.) → null
        if (preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})$/', $date, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }
}
