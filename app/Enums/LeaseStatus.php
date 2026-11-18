<?php

namespace App\Enums;

enum LeaseStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
    case Terminated = 'terminated';
}
