<?php

declare(strict_types=1);

namespace App\Inventory\Core\Event;

use App\Inventory\Core\Enum\StockMovementType;

final readonly class StockMovementRecorded
{
    public function __construct(
        public string $movementId,
        public string $warehouseId,
        public string $productId,
        public StockMovementType $movementType,
        public string $qtyDeltaBase,
        public string $sourceType,
        public string $sourceId,
        public \DateTimeImmutable $happenedAt,
    ) {
    }
}
