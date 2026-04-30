<?php

declare(strict_types=1);

namespace App\Inventory\Core\Contract;

use App\Catalog\Entity\Product;
use Nubit\Platform\Exception\ServiceException;
use App\Inventory\Entity\Warehouse;
use App\Sales\Entity\SaleItem;
use App\Sales\Entity\SaleRecord;
use Doctrine\DBAL\Exception;

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

    /**
     * @throws Exception
     * @throws ServiceException
     */
    public function applyMovementFromSaleItem(
        SaleRecord $sale,
        SaleItem $item,
        int $lineNo,
        bool $enforceNonNegativeStock = true,
    ): void;
}
