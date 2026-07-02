<?php

declare(strict_types=1);

namespace App\Inventory\Core\Dto;

use App\Inventory\Core\Enum\StockMovementType;

final readonly class MovementDraft
{
    public function __construct(
        public string $warehouseId,
        public string $productId,
        public StockMovementType $movementType,
        public string $qtyDeltaBase,
        public int $unitId,
        public string $qtyOriginal,
        public string $sourceType,
        public string $sourceId,
        public ?string $note = null,
        public ?string $happenedAt = null,
    ) {}
}
