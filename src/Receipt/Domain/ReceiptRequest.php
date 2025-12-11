<?php

declare(strict_types=1);

namespace App\Receipt\Domain;

class ReceiptRequest
{
    public function __construct(
        public ReceiptType $type,
        public string $id,
    ) {
    }
}
