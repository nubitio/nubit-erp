<?php

declare(strict_types=1);

namespace App\AI\Dto;

final readonly class SalesSummaryDto
{
    public function __construct(
        public string $from,
        public string $to,
        public int $totalSales,
        public float $totalAmount,
        public float $averageTicket,
    ) {}
}
