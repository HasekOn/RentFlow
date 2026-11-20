<?php

namespace App\Notifications\Concerns;

use Carbon\CarbonInterface;

/**
 * Česká typografie v textech notifikací (#47): datum „15. 10. 2026“ s nezlomitelnými mezerami. Částky přes
 * Money::format(). Až přibudou helpery cz_* (fáze 3), nahradí tuhle metodu.
 */
trait CzechText
{
    protected function czDate(?CarbonInterface $date): string
    {
        return $date?->format("j.\u{00A0}n.\u{00A0}Y") ?? '';
    }
}
