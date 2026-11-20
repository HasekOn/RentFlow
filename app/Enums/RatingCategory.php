<?php

namespace App\Enums;

enum RatingCategory: string
{
    case ApartmentCondition = 'apartment_condition';
    case Communication = 'communication';
    case Rules = 'rules';
    case Overall = 'overall';
}
