<?php

declare(strict_types=1);

namespace App\Inventory\Core\Enum;

enum LotTrackingStrategy: string
{
    case NONE = 'NONE';
    case FIFO = 'FIFO'; // First In, First Out  — deduct oldest received lot first
    case FEFO = 'FEFO'; // First Expired, First Out — deduct nearest-expiry lot first
}
