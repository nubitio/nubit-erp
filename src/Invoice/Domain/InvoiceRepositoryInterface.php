<?php

namespace App\Invoice\Domain;

use App\Entity\Invoice;
use App\Entity\Sale;

interface InvoiceRepositoryInterface
{
    public function getOneBySaleId(string $saleId): Invoice;

    public function create(Sale $sale): void;

    public function getVoucher(string $saleId): Voucher;
}
