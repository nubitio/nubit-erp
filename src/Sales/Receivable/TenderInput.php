<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Catalog\Entity\PaymentMethod;

/** One way the money arrived: a method, an amount and, optionally, the Treasury account it landed in. */
final readonly class TenderInput
{
    public function __construct(
        public PaymentMethod $method,
        public string $amount,
        public ?string $moneyAccountId = null,
        public ?string $reference = null,
    ) {}
}
