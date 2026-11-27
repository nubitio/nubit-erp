<?php

declare(strict_types=1);

namespace App\Efact\Domain\Finance;

use Brick\Math\BigDecimal;
use DateTimeImmutable;

/** Something that can still be settled: a receivable or payable instalment, reduced to what allocation needs. */
final readonly class OpenItem
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $dueDate,
        public DateTimeImmutable $issuedOn,
        public BigDecimal $open,
    ) {}
}
