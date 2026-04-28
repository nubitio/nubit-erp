<?php

declare(strict_types=1);

namespace App\CashRegister\Enum;

enum CashMovementType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
}
