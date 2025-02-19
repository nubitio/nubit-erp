<?php

namespace App\Enum;

enum CashMovementType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
}
