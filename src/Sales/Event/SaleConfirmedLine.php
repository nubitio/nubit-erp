<?php

declare(strict_types=1);

namespace App\Sales\Event;

final readonly class SaleConfirmedLine
{
    public function __construct(
        public string $productId,
        public string $quantity,
    ) {}
}
