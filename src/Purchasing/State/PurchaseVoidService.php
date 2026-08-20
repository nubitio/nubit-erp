<?php

declare(strict_types=1);

namespace App\Purchasing\State;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Dto\MovementDraft;
use App\Inventory\Core\Enum\InventoryBehavior;
use App\Inventory\Core\Enum\StockMovementType;
use App\Inventory\Core\Service\InventoryService;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseItem;
use Psr\Log\LoggerInterface;

/**
 * Reverses inventory movements when a purchase (goods receipt) is voided.
 *
 * For each item that affects inventory a PURCHASE_VOID movement is generated
 * with qty_delta = -original_qty, cancelling the original PURCHASE_IN exactly.
 *
 * Idempotent: the sourceId 'PURCHASE_VOID:{purchaseId}:{lineNo}' collides with
 * an existing idempotency_key if this method is called twice → silently skipped.
 */
final readonly class PurchaseVoidService implements PurchaseVoidServiceInterface
{
    public function __construct(
        private InventoryService $inventoryService,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function reverse(Purchase $purchase): void
    {
        $warehouse = $purchase->getWarehouse();
        if ($warehouse === null) {
            $this->logger->info('PurchaseVoidService: no warehouse on purchase, skipping', [
                'purchase_id' => $purchase->getId(),
            ]);

            return;
        }

        $purchaseId = (string) $purchase->getId();
        $warehouseId = (string) $warehouse->getId();
        $lineNo = 0;
        $reversed = 0;

        foreach ($purchase->getItems() as $item) {
            /** @var PurchaseItem $item */
            ++$lineNo;

            $product = $item->getProduct();
            if (!$product instanceof Product || !$product->getTrackInventory()) {
                continue;
            }

            $qty = (float) $item->getQty();
            if ($qty <= 0.0) {
                continue;
            }

            $qtyString = number_format($qty, 6, '.', '');
            $negativeDelta = '-' . $qtyString;

            try {
                $this->inventoryService->recordMovement(
                    new MovementDraft(
                        warehouseId: $warehouseId,
                        productId: (string) $product->getId(),
                        movementType: StockMovementType::PURCHASE_VOID,
                        qtyDeltaBase: $negativeDelta,
                        unitId: 0,
                        qtyOriginal: $qtyString,
                        sourceType: 'PURCHASE_VOID',
                        sourceId: sprintf('%s:%d', $purchaseId, $lineNo),
                        note: 'Anulación de compra ' . $purchaseId,
                        happenedAt: $purchase->getIssuedAt()?->format('Y-m-d H:i:s'),
                    ),
                    behavior: InventoryBehavior::TRACKED,
                );
                ++$reversed;
            } catch (\Throwable $e) {
                $this->logger->error('PurchaseVoidService: failed to reverse movement', [
                    'purchase_id' => $purchaseId,
                    'line_no' => $lineNo,
                    'product_id' => $product->getId(),
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        $this->logger->info('PurchaseVoidService: reversed purchase stock', [
            'purchase_id' => $purchaseId,
            'lines_reversed' => $reversed,
        ]);
    }
}
