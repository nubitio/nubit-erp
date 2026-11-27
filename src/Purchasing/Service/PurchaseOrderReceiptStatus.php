<?php

declare(strict_types=1);

namespace App\Purchasing\Service;

use App\Purchasing\Entity\PurchaseOrder;
use Brick\Math\BigDecimal;

final readonly class PurchaseOrderReceiptStatus
{
    public static function derive(PurchaseOrder $purchaseOrder): string
    {
        $anyReceived = false;
        $allReceived = true;

        foreach ($purchaseOrder->getItems() as $item) {
            $received = BigDecimal::of($item->getReceivedQty());
            $ordered = BigDecimal::of($item->getOrderedQty());

            if ($received->isPositive()) {
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
