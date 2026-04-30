<?php

declare(strict_types=1);

namespace App\Billing\Receipt\Domain;

class ReceiptRequest
{
    public function __construct(
        public ReceiptType $type,
        public string $id,
    ) {
    }
}
