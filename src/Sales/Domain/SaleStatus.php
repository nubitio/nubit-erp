<?php

declare(strict_types=1);

namespace App\Sales\Domain;

enum SaleStatus: int
{
    case ACTIVE = 1;
    case VOIDED = 2;
    // Add other sale statuses as needed
}
