<?php

namespace App\Receipt\Domain;

class Receipt
{
    public function __construct(
        public string $company,
        public string $type,
        public string $series,
        public string $number,
        public string $date,
        public string $total,
        public string $pdf,
        public string $xml,
        public string $cdr,
    ) {
    }
}
