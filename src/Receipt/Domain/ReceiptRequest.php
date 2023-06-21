<?php

namespace App\Receipt\Domain;

class ReceiptRequest
{
    public function __construct(
        public ReceiptType $type,
        public string $id,
    ) {
    }
}
