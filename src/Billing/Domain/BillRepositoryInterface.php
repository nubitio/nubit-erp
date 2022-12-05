<?php

namespace App\Billing\Domain;

use App\Billing\Model\Bill\Bill;
use App\Billing\Model\Response\DocumentResponse;

interface BillRepositoryInterface
{
    function createBill(Bill $invoice): DocumentResponse;
}