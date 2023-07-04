<?php

namespace App\Billing\Domain;

use App\Billing\Model\Response\DocumentResponse;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Sale\Invoice;

interface BillRepositoryInterface
{
    public function sendBill(Invoice $invoice): DocumentResponse;

    public function getXml(Invoice $invoice): string;

    public function getPdf(Invoice $invoice): string;

    public function getStatus(Invoice $invoice): StatusCdrResult;
}
