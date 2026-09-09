<?php

declare(strict_types=1);

namespace App\Sales\Report\CustomerStatement;

/**
 * One movement in a customer account statement: a charge (a credit sale) or a
 * payment (an abono against one of those sales). `balance` is the running
 * balance after this line.
 */
final readonly class StatementLine
{
    public function __construct(
        public string $date,
        public string $type,
        public string $document,
        public string $detail,
        public string $charge,
        public string $payment,
        public string $balance,
    ) {}
}
