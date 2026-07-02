<?php

declare(strict_types=1);

namespace App\Inventory\Core\Event;

final readonly class SaleCompleted
{
    public function __construct(
        public string $saleId,
        public string $warehouseId,
        public \DateTimeImmutable $happenedAt,
    ) {}
}
