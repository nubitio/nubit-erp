<?php

declare(strict_types=1);

namespace App\Inventory\Core\Enum;

enum StockMovementType: string
{
    case PURCHASE = 'PURCHASE_IN';
    case SALE = 'SALE_OUT';
    case ADJUSTMENT = 'ADJUSTMENT';
    case TRANSFER_IN = 'TRANSFER_IN';
    case TRANSFER_OUT = 'TRANSFER_OUT';
    case RECIPE_CONSUMPTION = 'RECIPE_CONSUMPTION';
}
