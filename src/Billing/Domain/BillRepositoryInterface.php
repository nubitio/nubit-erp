<?php

namespace App\Billing\Domain;

use App\Billing\Model\Bill\Bill;
use App\Billing\Model\Response\DocumentResponse;
use Greenter\Model\Response\StatusCdrResult;

interface BillRepositoryInterface
{
    public function sendBill(Bill $invoice): DocumentResponse;

    public function getXml(Bill $invoice): string;

    public function getPdf(Bill $invoice): string;

    public function getStatus(Bill $invoice): StatusCdrResult;
}
