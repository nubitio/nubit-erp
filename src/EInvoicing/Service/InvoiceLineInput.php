<?php

declare(strict_types=1);

namespace App\EInvoicing\Service;

final class InvoiceLineInput
{
    public function __construct(
        public string $quantity,
        public string $unitPrice,
    ) {
    }
}
