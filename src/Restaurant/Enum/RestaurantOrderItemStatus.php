<?php

declare(strict_types=1);

namespace App\Restaurant\Enum;

/**
 * Lifecycle of one comanda line, from the waiter's tap to the kitchen and
 * back (NB-18).
 */
enum RestaurantOrderItemStatus: string
{
    case Pending = 'PENDING';
    case Sent = 'SENT';
    case Ready = 'READY';
    case Delivered = 'DELIVERED';
}
