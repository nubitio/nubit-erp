<?php

declare(strict_types=1);

namespace App\Reporting\Sire;

use Brick\Math\BigDecimal;

/** Importes positivos de un comprobante de venta, ya clasificados por afectación. */
final readonly class RvieAmounts
{
    public function __construct(
        public BigDecimal $export,
        public BigDecimal $taxable,
        public BigDecimal $igv,
        public BigDecimal $exempt,
        public BigDecimal $unaffected,
        public BigDecimal $total,
    ) {}

    public static function of(
        string $total,
        string $taxable = '0',
        string $igv = '0',
        string $exempt = '0',
        string $unaffected = '0',
        string $export = '0',
    ): self {
        return new self(
            BigDecimal::of($export),
            BigDecimal::of($taxable),
            BigDecimal::of($igv),
            BigDecimal::of($exempt),
            BigDecimal::of($unaffected),
            BigDecimal::of($total),
        );
    }

    /** Suma de los componentes que deberían igualar al total. */
    public function componentsSum(): BigDecimal
    {
        return $this->export->plus($this->taxable)->plus($this->igv)->plus($this->exempt)->plus($this->unaffected);
    }
}
