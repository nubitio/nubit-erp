<?php

declare(strict_types=1);

namespace App\Purchasing\Service;

use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseOrder;
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
        $orderLines = [];
        foreach ($purchase->getPurchaseOrder()?->getItems() ?? [] as $orderItem) {
            $orderLines[$orderItem->getId()] = $orderItem;
        }
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
            $purchaseOrder->setStatus($this->deriveStatus($purchaseOrder));
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
            $purchaseOrder->setStatus($this->deriveStatus($purchaseOrder));
        }

        $this->entityManager->flush();
    }

    private function deriveStatus(PurchaseOrder $purchaseOrder): string
    {
        $anyReceived = false;
        $allReceived = true;

        foreach ($purchaseOrder->getItems() as $item) {
            $received = BigDecimal::of($item->getReceivedQty());
            $ordered = BigDecimal::of($item->getOrderedQty());

            if ($received->isGreaterThan(BigDecimal::zero())) {
                $anyReceived = true;
            }
            if ($received->isLessThan($ordered)) {
                $allReceived = false;
            }
        }

        if ($allReceived && $anyReceived) {
            return PurchaseOrder::STATUS_RECEIVED;
        }
        if ($anyReceived) {
            return PurchaseOrder::STATUS_PARTIALLY_RECEIVED;
        }

        // Nothing received (e.g. after a full revert) — fall back to 'sent'
        // rather than leaving a receipt-driven status with zero receipts.
        return PurchaseOrder::STATUS_SENT;
    }
}
