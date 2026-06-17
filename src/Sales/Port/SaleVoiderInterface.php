<?php

declare(strict_types=1);

namespace App\Sales\Port;

/**
 * Voids (annuls) a sale and its associated electronic document.
 */
interface SaleVoiderInterface
{
    /**
     * @throws \Throwable
     */
    public function voidSale(string $saleId, string $reason, int $userId, bool $restoreStock = false): void;
}
