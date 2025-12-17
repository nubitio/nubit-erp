<?php

declare(strict_types=1);

namespace App\Dto\Assistant;

final class RecentSaleDto
{
    public function __construct(
        public readonly string $id,
        public readonly float $total,
        public readonly string $issuedAt,
        public readonly ?string $invoice,
        public readonly ?string $series,
        public readonly ?string $number,
    ) {
    }
}
