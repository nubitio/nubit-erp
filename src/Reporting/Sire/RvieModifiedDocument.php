<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

use DateTimeImmutable;

/** Comprobante que modifica una nota de crédito o de débito. */
final readonly class RvieModifiedDocument
{
    public function __construct(
        public DateTimeImmutable $date,
        public string $type,
        public string $series,
        public string $number,
    ) {}
}
