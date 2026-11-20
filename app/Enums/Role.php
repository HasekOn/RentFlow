<?php

namespace App\Enums;

enum Role: string
{
    case Landlord = 'landlord';
    case Manager = 'manager';
    case Tenant = 'tenant';
}
