<?php

namespace App\Enum;

enum CashShiftStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
}
