<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

use Brick\Math\BigDecimal;
use DateTimeImmutable;

/**
 * Un comprobante de compra ya normalizado para el RCE. Los importes son
 * positivos; el signo de la nota de crédito lo decide el constructor de línea.
 */
final readonly class RceDocument
{
    public function __construct(
        public string $type,
        public string $series,
        public string $number,
        public DateTimeImmutable $issueDate,
        public SireParty $supplier,
        public BigDecimal $taxable,
        public BigDecimal $igv,
        public BigDecimal $notTaxed,
        public BigDecimal $total,
        public string $currency = 'PEN',
        public ?string $exchangeRate = null,
    ) {}

    public function isCreditNote(): bool
    {
        return $this->type === '07';
    }
}
