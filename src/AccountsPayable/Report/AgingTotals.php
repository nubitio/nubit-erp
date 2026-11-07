<?php

declare(strict_types=1);

namespace App\AccountsPayable\Report;

use App\Efact\Domain\Money;
use Brick\Math\BigDecimal;

/** Acumulado de saldos por tramo de antigüedad de un proveedor (o de una moneda). */
final class AgingTotals
{
    /** @var array<string, BigDecimal> */
    private array $buckets = [];

    private BigDecimal $total;

    private int $billCount = 0;

    public function __construct(
        private readonly string $supplierId,
        private readonly string $ruc,
        private readonly string $supplier,
        private readonly string $currency,
    ) {
        $this->total = BigDecimal::zero();
        foreach (PayablesAgingCalculator::BUCKETS as $bucket) {
            $this->buckets[$bucket] = BigDecimal::zero();
        }
    }

    public function add(string $bucket, BigDecimal $balance): void
    {
        $this->buckets[$bucket] = ($this->buckets[$bucket] ?? BigDecimal::zero())->plus($balance);
        $this->total = $this->total->plus($balance);
        ++$this->billCount;
    }

    /** @return array<string, string|int> */
    public function toRow(): array
    {
        $row = [
            'supplierId' => $this->supplierId,
            'ruc' => $this->ruc,
            'supplier' => $this->supplier,
            'currency' => $this->currency,
            'billCount' => $this->billCount,
        ];
        foreach ($this->buckets as $bucket => $amount) {
            $row[$bucket] = Money::toString($amount);
        }
        $row['total'] = Money::toString($this->total);

        return $row;
    }
}
