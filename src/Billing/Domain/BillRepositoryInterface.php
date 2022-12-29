<?php

namespace App\Billing\Domain;

use App\Billing\Model\Bill\Bill;
use App\Billing\Model\Response\DocumentResponse;

interface BillRepositoryInterface
{
    public function sendBill(Bill $invoice): DocumentResponse;

    public function getPdf(Bill $invoice): string;
}
