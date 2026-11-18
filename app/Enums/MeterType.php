<?php

namespace App\Enums;

enum MeterType: string
{
    case Water = 'water';
    case Electricity = 'electricity';
    case Gas = 'gas';
    case Heat = 'heat';
}
