<?php

declare(strict_types=1);

namespace App\AI\Dto;

final readonly class RecentSaleDto
{
    public function __construct(
        public string $id,
        public float $total,
        public string $issuedAt,
        public ?string $invoice,
        public ?string $series,
        public ?string $number,
    ) {
    }
}
