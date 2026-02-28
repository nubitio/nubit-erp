<?php

declare(strict_types=1);

namespace App\Inventory\Core\Event;

final readonly class PurchaseReceived
{
    public function __construct(
        public string $purchaseId,
        public string $warehouseId,
        public \DateTimeImmutable $happenedAt,
    ) {
    }
}
