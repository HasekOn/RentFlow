<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Renovation = 'renovation';
}
