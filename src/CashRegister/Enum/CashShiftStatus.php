<?php

declare(strict_types=1);

namespace App\CashRegister\Enum;

enum CashShiftStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
}
