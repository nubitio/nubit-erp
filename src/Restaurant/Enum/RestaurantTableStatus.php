<?php

declare(strict_types=1);

namespace App\Restaurant\Enum;

/**
 * Lifecycle of a dining table (NB-18). A table holds at most one open order
 * at a time; `AwaitingPayment` means the guest asked for the bill but the
 * table has not been closed/charged yet.
 */
enum RestaurantTableStatus: string
{
    case Free = 'FREE';
    case Occupied = 'OCCUPIED';
    case AwaitingPayment = 'AWAITING_PAYMENT';
}
