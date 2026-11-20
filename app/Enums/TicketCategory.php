<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Plumbing = 'plumbing';
    case Electrical = 'electrical';
    case Heating = 'heating';
    case Structural = 'structural';
    case Appliance = 'appliance';
    case Other = 'other';
}
