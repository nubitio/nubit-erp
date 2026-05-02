<?php

declare(strict_types=1);

namespace App\Inventory\Core\Contract;

use App\Sales\Entity\SaleRecord;
use App\Inventory\Core\Dto\MovementDraft;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Enrichers are collected by InventoryService and applied in order.
 * Implement this interface to inject BOM explosion, lot selection, etc.
 */
#[AutoconfigureTag('inventory.stock_movement_enricher')]
interface StockMovementEnricherInterface
{
    /**
     * @param list<MovementDraft> $baseMovements
     *
     * @return list<MovementDraft>
     */
    public function enrichSaleMovements(SaleRecord $sale, array $baseMovements): array;
}
