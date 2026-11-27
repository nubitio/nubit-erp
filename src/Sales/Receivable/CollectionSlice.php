<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use Brick\Math\BigDecimal;

/**
 * Part of one tender that settles one allocation, or, when `allocationIndex` is null, the part that settles
 * nothing and stays as the customer's advance. The distribution among tenders is independent of the one among
 * invoices: a 2,500 collection can be 1,000 cash plus 1,500 transfer whatever it settles.
 */
final readonly class CollectionSlice
{
    public function __construct(
        public int $tenderIndex,
        public ?int $allocationIndex,
        public BigDecimal $amount,
    ) {}
}
