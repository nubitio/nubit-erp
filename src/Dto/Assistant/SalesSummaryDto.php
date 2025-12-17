<?php

declare(strict_types=1);

namespace App\Dto\Assistant;

final class SalesSummaryDto
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly int $totalSales,
        public readonly float $totalAmount,
        public readonly float $averageTicket,
    ) {
    }
}
