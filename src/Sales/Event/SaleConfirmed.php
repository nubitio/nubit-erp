<?php

declare(strict_types=1);

namespace App\Sales\Event;

/**
 * Emitted by Sales once a new sale is persisted. Consuming contexts (Pharmacy's
 * batch drawdown, NB-12; future loyalty/analytics) subscribe with their own
 * handlers — Sales never imports them.
 */
final readonly class SaleConfirmed
{
    /**
     * @param list<SaleConfirmedLine> $lines
     */
    public function __construct(
        public string $saleId,
        public string $warehouseId,
        public array $lines,
    ) {}
}
