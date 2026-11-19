<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Peníze v haléřích (#49): sloupce jsou `decimal` (z DB přijdou jako řetězec „15000.00“), rozdíly a porovnání
 * se počítají v `int`, nikdy přes float. Součty dělá DB (`SUM`), výsledek se sem převede.
 */
final class Money
{
    /**
     * „1234.5“, „-80.00“, 1234 → haléře. Třetí desetinné místo se zaokrouhlí (half up).
     */
    public static function cents(string|int|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }
        if (is_int($amount)) {
            return $amount * 100;
        }
        if (! preg_match('/^(-)?(\d*)(?:\.(\d*))?$/', trim($amount), $parts) || $parts[2].($parts[3] ?? '') === '') {
            throw new InvalidArgumentException("Neplatná částka: {$amount}");
        }

        $fraction = str_pad($parts[3] ?? '', 3, '0');
        $cents = (int) $parts[2] * 100 + (int) substr($fraction, 0, 2) + ((int) $fraction[2] >= 5 ? 1 : 0);

        return $parts[1] === '-' ? -$cents : $cents;
    }

    /**
     * Výsledek `SUM` z DB → haléře. Driver vrací podle DB int, float nebo řetězec (SQLite float, MariaDB řetězec);
     * float se hned na hranici zaokrouhlí na dvě místa a dál se počítá v int.
     */
    public static function fromSum(mixed $sum): int
    {
        return match (true) {
            $sum === null => 0,
            is_int($sum) => $sum * 100,
            is_float($sum) => self::cents(number_format($sum, 2, '.', '')),
            default => self::cents((string) $sum),
        };
    }

    /**
     * Haléře → desetinný řetězec pro DB a API („1234.50“).
     */
    public static function fromCents(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Haléře → česky „12 500 Kč“ / „12 500,50 Kč“ s nezlomitelnými mezerami (jeden formát pro e-maily, PDF i UI).
     */
    public static function format(int $cents): string
    {
        $decimals = $cents % 100 === 0 ? 0 : 2;

        return number_format($cents / 100, $decimals, ',', "\u{00A0}")."\u{00A0}Kč";
    }

    /**
     * Haléře → číslo do JSON (API vrací částky jako číslo). Jen pro výstup, nepočítat s ním.
     */
    public static function toFloat(int $cents): float
    {
        return $cents / 100;
    }
}
