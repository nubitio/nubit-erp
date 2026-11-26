<?php

declare(strict_types=1);

namespace App\Purchasing\Service;

use App\Purchasing\Entity\Purchase;
use Nubit\Platform\Exception\ValidationException;

final class PurchaseReceiptLines
{
    public static function resolve(Purchase $purchase): void
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
}
