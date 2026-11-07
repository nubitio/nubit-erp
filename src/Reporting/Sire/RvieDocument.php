<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

use DateTimeImmutable;

/**
 * Un comprobante de venta ya normalizado para el RVIE. Los importes son
 * positivos; el signo de la nota de crédito lo decide el constructor de línea.
 */
final readonly class RvieDocument
{
    public function __construct(
        public string $type,
        public string $series,
        public string $number,
        public DateTimeImmutable $issueDate,
        public SireParty $customer,
        public RvieAmounts $amounts,
        public string $currency = 'PEN',
        public ?string $exchangeRate = null,
        public ?RvieModifiedDocument $modified = null,
    ) {}

    public function isCreditNote(): bool
    {
        return $this->type === '07';
    }

    public function isNote(): bool
    {
        return in_array($this->type, ['07', '08'], true);
    }
}
