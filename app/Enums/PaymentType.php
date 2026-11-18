<?php

namespace App\Enums;

enum PaymentType: string
{
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Deposit = 'deposit';
    case Other = 'other';
}
