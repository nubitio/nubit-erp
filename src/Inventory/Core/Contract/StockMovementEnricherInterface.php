<?php

declare(strict_types=1);

namespace App\Inventory\Core\Contract;

use App\Entity\SaleRecord;
use App\Inventory\Core\Dto\MovementDraft;

interface StockMovementEnricherInterface
{
    /**
     * @param list<MovementDraft> $baseMovements
     *
     * @return list<MovementDraft>
     */
    public function enrichSaleMovements(SaleRecord $sale, array $baseMovements): array;
}
