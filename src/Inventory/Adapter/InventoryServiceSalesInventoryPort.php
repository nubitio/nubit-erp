<?php

declare(strict_types=1);

namespace App\Inventory\Adapter;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Contract\SalesInventoryPortInterface;
use App\Inventory\Core\Dto\MovementDraft;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Service\InventoryService;
use App\Inventory\Entity\Warehouse;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleRecord;
use Doctrine\DBAL\Exception;
use Nubit\Platform\Exception\ServiceException;

/**
 * Inventory V2 adapter for the sale inventory port used by Sales.
 *
 * Keeps the old SaleInventoryOrchestrator contract stable while moving the
 * underlying write/read path to InventoryService V2 per ADR-018.
 */
final readonly class InventoryServiceSalesInventoryPort implements SalesInventoryPortInterface
{
    public function __construct(
        private InventoryService $inventoryService,
    ) {}

    public function getStockForProductInWarehouse(Product $product, Warehouse $warehouse): float
    {
        return (float) $this->inventoryService->getOnHand((string) $warehouse->getId(), (string) $product->getId());
    }

    public function lockAvailableStock(Product $product, Warehouse $warehouse): string
    {
        return $this->inventoryService->lockAvailableStock((string) $warehouse->getId(), (string) $product->getId());
    }

    /**
     * @throws Exception
     * @throws ServiceException
     */
    public function applyMovementFromSaleItem(
        SaleRecord $sale,
        SaleItemRecord $item,
        int $lineNo,
        bool $enforceNonNegativeStock = true,
    ): void {
        $warehouse = $sale->getAlmacen();
        $product = $item->getProduct();
        if ($warehouse === null || $product === null) {
            return;
        }

        $movementType = $sale->getTipoId() === 1 ? StockMovementType::PURCHASE : StockMovementType::SALE;
        $qtyOriginal = number_format((float) $item->getQty(), 6, '.', '');
        $qtyDeltaBase = $movementType === StockMovementType::PURCHASE ? $qtyOriginal : '-' . $qtyOriginal;
        $saleId = (string) ($sale->getId() ?? 'pending-sale');

        $draft = new MovementDraft(
            warehouseId: (string) $warehouse->getId(),
            productId: (string) $product->getId(),
            movementType: $movementType,
            qtyDeltaBase: $qtyDeltaBase,
            unitId: 0,
            qtyOriginal: $qtyOriginal,
            sourceType: 'SALE',
            sourceId: sprintf('%s:%s:%d', $saleId, $movementType->value, $lineNo),
            note: 'Applied by InventoryServiceSalesInventoryPort',
            happenedAt: $sale->getFecha()?->format('Y-m-d H:i:s'),
        );

        $behavior = InventoryBehavior::tryFrom($product->getInventoryBehavior()) ?? InventoryBehavior::TRACKED;

        $this->inventoryService->recordMovement($draft, $sale, $behavior);
    }
}
