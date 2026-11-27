<?php

declare(strict_types=1);

namespace App\Efact\Domain\Finance;

use Brick\Math\BigDecimal;

/** How much of an amount settles one open item. */
final readonly class Allocation
{
    public function __construct(
        public string $itemId,
        public BigDecimal $amount,
    ) {}
}
