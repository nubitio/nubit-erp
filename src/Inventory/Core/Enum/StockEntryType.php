<?php

declare(strict_types=1);

namespace App\Inventory\Core\Enum;

/**
 * Types of manual stock entry documents.
 *
 * RECEIPT    — manual inbound not tied to a supplier invoice
 *              (production, customer return, donation…)
 *              → generates MANUAL_IN ledger movements
 *
 * ISSUE      — manual outbound not tied to a sale
 *              (sample, internal consumption, documented waste…)
 *              → generates MANUAL_OUT ledger movements
 *
 * TRANSFER   — move stock between two warehouses of the same company
 *              → generates TRANSFER_OUT (source) + TRANSFER_IN (target)
 *
 * ADJUSTMENT — correct stock to match physical count
 *              qty entered is the new absolute quantity;
 *              the processor computes the signed delta
 *              → generates ADJUSTMENT ledger movement
 */
enum StockEntryType: string
{
    case RECEIPT    = 'RECEIPT';
    case ISSUE      = 'ISSUE';
    case TRANSFER   = 'TRANSFER';
    case ADJUSTMENT = 'ADJUSTMENT';

    public function label(): string
    {
        return match ($this) {
            self::RECEIPT    => 'Entrada manual',
            self::ISSUE      => 'Salida manual',
            self::TRANSFER   => 'Traslado',
            self::ADJUSTMENT => 'Ajuste de stock',
        };
    }

    /** Returns the corresponding StockMovementType(s) for this entry type. */
    /** @return StockMovementType[] */
    public function movementTypes(): array
    {
        return match ($this) {
            self::RECEIPT    => [StockMovementType::MANUAL_IN],
            self::ISSUE      => [StockMovementType::MANUAL_OUT],
            self::TRANSFER   => [StockMovementType::TRANSFER_OUT, StockMovementType::TRANSFER_IN],
            self::ADJUSTMENT => [StockMovementType::ADJUSTMENT],
        };
    }

    public function requiresTargetWarehouse(): bool
    {
        return $this === self::TRANSFER;
    }

    public function requiresReason(): bool
    {
        return $this === self::ADJUSTMENT;
    }

    public function usesAbsoluteQty(): bool
    {
        return $this === self::ADJUSTMENT;
    }

    public function validatesStock(): bool
    {
        return match ($this) {
            self::ISSUE, self::TRANSFER => true,
            default                     => false,
        };
    }
}
