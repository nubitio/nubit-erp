<?php

declare(strict_types=1);

namespace App\Inventory\Core\Contract;

use App\Catalog\Entity\Product;
use App\Inventory\Entity\Warehouse;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleRecord;
use Doctrine\DBAL\Exception;
use Nubit\Platform\Exception\ServiceException;

/**
 * Port for sale inventory operations used by Sales during the ADR-018 cutover.
 * Production wiring delegates to InventoryService V2.
 */
interface SalesInventoryPortInterface
{
    /**
     * @throws Exception
     */
    public function getStockForProductInWarehouse(Product $product, Warehouse $warehouse): float;

    /** @return numeric-string Available stock (net of reservations), locked until the caller commits. */
    public function lockAvailableStock(Product $product, Warehouse $warehouse): string;

    /**
     * @throws Exception
     * @throws ServiceException
     */
    public function applyMovementFromSaleItem(
        SaleRecord $sale,
        SaleItemRecord $item,
        int $lineNo,
        bool $enforceNonNegativeStock = true,
    ): void;
}
