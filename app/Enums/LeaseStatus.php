<?php

namespace App\Enums;

/**
 * Nová smlouva je pozvánka (`Pending`), aktivní je až po přijetí nájemníkem, `Declined` po odmítnutí (S1).
 */
enum LeaseStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Ended = 'ended';
    case Terminated = 'terminated';
    case Declined = 'declined';
}
