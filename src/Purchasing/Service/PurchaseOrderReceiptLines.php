<?php

declare(strict_types=1);

namespace App\Purchasing\Service;

use App\Purchasing\Entity\Purchase;
use App\Purchasing\Entity\PurchaseOrderItem;

final class PurchaseOrderReceiptLines
{
    /**
     * @return array<int, PurchaseOrderItem>
     */
    public static function index(Purchase $purchase): array
    {
        $lines = [];
        foreach ($purchase->getPurchaseOrder()?->getItems() ?? [] as $line) {
            $id = $line->getId();
            if ($id !== null) {
                $lines[$id] = $line;
            }
        }

        return $lines;
    }
}
