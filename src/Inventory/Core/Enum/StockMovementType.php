<?php

declare(strict_types=1);

namespace App\Inventory\Core\Enum;

enum StockMovementType: string
{
    case PURCHASE          = 'PURCHASE_IN';
    case SALE              = 'SALE_OUT';
    case ADJUSTMENT        = 'ADJUSTMENT';
    case TRANSFER_IN       = 'TRANSFER_IN';
    case TRANSFER_OUT      = 'TRANSFER_OUT';
    case RECIPE_CONSUMPTION = 'RECIPE_CONSUMPTION';
    case MANUAL_IN         = 'MANUAL_IN';
    case MANUAL_OUT        = 'MANUAL_OUT';
    case VOID_REVERSAL     = 'VOID_REVERSAL';
    case PURCHASE_VOID     = 'PURCHASE_VOID';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE           => 'Compra',
            self::SALE               => 'Venta',
            self::ADJUSTMENT         => 'Ajuste',
            self::TRANSFER_IN        => 'Traslado entrada',
            self::TRANSFER_OUT       => 'Traslado salida',
            self::RECIPE_CONSUMPTION => 'Consumo receta',
            self::MANUAL_IN          => 'Entrada manual',
            self::MANUAL_OUT         => 'Salida manual',
            self::VOID_REVERSAL      => 'Reversa anulación',
            self::PURCHASE_VOID      => 'Anulación compra',
        };
    }

    /** Returns true when the movement adds stock (+). */
    public function isInbound(): bool
    {
        return match ($this) {
            self::PURCHASE, self::TRANSFER_IN, self::MANUAL_IN, self::VOID_REVERSAL, self::ADJUSTMENT => true,
            default => false,
        };
    }
}
