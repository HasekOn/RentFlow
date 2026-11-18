<?php

namespace App\Enums;

enum DocumentVisibility: string
{
    case LandlordOnly = 'landlord_only';
    case LandlordTenant = 'landlord_tenant';
    case LandlordManager = 'landlord_manager';
    case All = 'all';
}
