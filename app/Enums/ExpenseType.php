<?php

namespace App\Enums;

enum ExpenseType: string
{
    case Repair = 'repair';
    case Insurance = 'insurance';
    case Tax = 'tax';
    case Maintenance = 'maintenance';
    case Other = 'other';
}
