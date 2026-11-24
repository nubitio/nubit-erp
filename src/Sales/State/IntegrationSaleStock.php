<?php

declare(strict_types=1);

namespace App\Sales\State;

use App\Inventory\Core\Contract\SalesInventoryPortInterface;
use App\Inventory\Entity\Warehouse;
use App\Sales\Entity\SaleRecord;
use Brick\Math\BigDecimal;
use Nubit\Platform\Exception\ServiceException;

/** External intake always checks available stock under a lock, independently of POS overrides. */
final readonly class IntegrationSaleStock
{
    public function __construct(
        private SalesInventoryPortInterface $inventory,
        private NegativeStockPolicyResolver $policy,
    ) {}

    public function record(SaleRecord $sale, Warehouse $warehouse): void
    {
        $demand = [];
        $trackedLines = [];
        foreach ($sale->getSaleItemRecords()->getValues() as $index => $item) {
            $product = $item->getProduct();
            if ($product === null || !$this->policy->isStockTracked($product)) {
                continue;
            }
            $id = (string) $product->getId();
            $demand[$id] = [$product, ($demand[$id][1] ?? BigDecimal::zero())->plus($item->getQty())];
            $trackedLines[$index + 1] = $item;
        }
        ksort($demand, SORT_STRING);
        foreach ($demand as [$product, $quantity]) {
            if ($quantity->isGreaterThan($this->inventory->lockAvailableStock($product, $warehouse))) {
                throw new ServiceException(sprintf('Stock disponible insuficiente para "%s".', $product->getName()));
            }
        }
        foreach ($trackedLines as $line => $item) {
            $this->inventory->applyMovementFromSaleItem($sale, $item, $line);
        }
    }
}
