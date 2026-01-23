<?php

declare(strict_types=1);

namespace App\Enum;

enum MovementType: int
{
    case PURCHASE = 1;
    case SALE = 2;

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Compra',
            self::SALE => 'Venta',
        };
    }
}
