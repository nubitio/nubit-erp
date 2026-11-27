<?php

declare(strict_types=1);

namespace App\Efact\Domain\Finance;

use Brick\Math\BigDecimal;

/** The split of an amount: what settles each item, and what is left over (a balance in the payer's favour). */
final readonly class AllocationResult
{
    /** @param list<Allocation> $allocations */
    public function __construct(
        public array $allocations,
        public BigDecimal $unapplied,
    ) {}
}
