<?php

declare(strict_types=1);

namespace App\Enum;

enum CashMovementType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
}
