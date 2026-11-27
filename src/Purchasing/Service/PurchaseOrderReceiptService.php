<?php

declare(strict_types=1);

namespace App\Purchasing\Service;

use App\Purchasing\Entity\Purchase;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ValidationException;

/**
 * Keeps PurchaseOrderItem.receivedQty and PurchaseOrder.status in sync
 * whenever a Purchase linked to a PurchaseOrder is posted or voided.
 * Deliberately separate from PurchaseInventoryService/PurchaseVoidService
 * — PO bookkeeping is independent of whether a product tracks inventory,
 * so it must NOT reuse those services' trackInventory skip.
 *
 * Quantity arithmetic uses BigDecimal directly at scale 3, matching
 * PurchaseOrderItem::orderedQty/receivedQty's column scale — NOT
 * App\Efact\Domain\Money, which is money-scale (2).
 */
readonly class PurchaseOrderReceiptService
{
    private const int QTY_SCALE = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function resolveReceiptLines(Purchase $purchase): void
    {
        $orderLines = PurchaseOrderReceiptLines::index($purchase);
        foreach ($purchase->getItems() as $item) {
            $id = $item->getPurchaseOrderItemId() ?? $item->getPurchaseOrderItem()?->getId();
            if ($id === null) {
                continue;
            }
            $orderItem = $orderLines[$id] ?? null;
            if ($orderItem === null || $orderItem->getProduct()?->getId() !== $item->getProduct()?->getId()) {
                throw ValidationException::withErrors([
                    'items' => ['El producto y la línea deben corresponder a la orden de compra seleccionada.'],
                ]);
            }
            $item->setPurchaseOrderItem($orderItem);
        }
    }

    public function applyReceipt(Purchase $purchase): void
    {
        foreach ($purchase->getItems() as $item) {
            $orderItem = $item->getPurchaseOrderItem();
            if ($orderItem === null) {
                continue;
            }

            $received = BigDecimal::of($orderItem->getReceivedQty())->plus(BigDecimal::of($item->getQty()))->toScale(
                self::QTY_SCALE,
                RoundingMode::HALF_UP,
            );
            $orderItem->setReceivedQty($received->__toString());
        }

        $purchaseOrder = $purchase->getPurchaseOrder();
        if ($purchaseOrder !== null) {
            $purchaseOrder->setStatus(PurchaseOrderReceiptStatus::derive($purchaseOrder));
        }

        $this->entityManager->flush();
    }

    public function revertReceipt(Purchase $purchase): void
    {
        foreach ($purchase->getItems() as $item) {
            $orderItem = $item->getPurchaseOrderItem();
            if ($orderItem === null) {
                continue;
            }

            $received = BigDecimal::of($orderItem->getReceivedQty())->minus(BigDecimal::of($item->getQty()))->toScale(
                self::QTY_SCALE,
                RoundingMode::HALF_UP,
            );
            if ($received->isNegative()) {
                $received = BigDecimal::zero()->toScale(self::QTY_SCALE);
            }
            $orderItem->setReceivedQty($received->__toString());
        }

        $purchaseOrder = $purchase->getPurchaseOrder();
        if ($purchaseOrder !== null) {
            $purchaseOrder->setStatus(PurchaseOrderReceiptStatus::derive($purchaseOrder));
        }

        $this->entityManager->flush();
    }
}
