<?php

declare(strict_types=1);

namespace App\Purchasing\State;

use App\Catalog\Entity\Product;
use App\Purchasing\Entity\Purchase;
use App\Inventory\Core\Dto\MovementDraft;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Service\InventoryService;

final readonly class PurchaseInventoryService
{
    public function __construct(
        private InventoryService $inventoryService,
    ) {
    }

    public function recordPurchaseReceipt(Purchase $purchase): void
    {
        $warehouse = $purchase->getWarehouse();
        if ($warehouse === null || $purchase->getStatus() === 'VOIDED') {
            return;
        }

        foreach ($purchase->getItems() as $item) {
            $lineNo = $item->getLineNo();
            if ($lineNo <= 0) {
                continue;
            }

            $product = $item->getProduct();
            if (!$product instanceof Product || !$product->getTrackInventory()) {
                continue;
            }

            $qty = (float) $item->getQty();
            if ($qty <= 0) {
                continue;
            }

            $purchaseId = (string) $purchase->getId();
            $productId = (string) $product->getId();
            $warehouseId = (string) $warehouse->getId();
            $qtyString = number_format($qty, 6, '.', '');

            $this->inventoryService->recordMovement(
                new MovementDraft(
                    warehouseId: $warehouseId,
                    productId: $productId,
                    movementType: StockMovementType::PURCHASE,
                    qtyDeltaBase: $qtyString,
                    unitId: 0,
                    qtyOriginal: $qtyString,
                    sourceType: 'PURCHASE_ITEM',
                    sourceId: sprintf('%s:%d', $purchaseId, $lineNo),
                    note: 'Applied from purchase receipt',
                    happenedAt: $purchase->getIssuedAt()?->format('Y-m-d H:i:s'),
                ),
                behavior: InventoryBehavior::TRACKED,
            );
        }
    }
}
