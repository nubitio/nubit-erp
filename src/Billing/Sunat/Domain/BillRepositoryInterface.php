<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Domain;

use App\Billing\Sunat\Model\Response\DocumentResponse;
use Greenter\Model\Response\StatusCdrResult;
use Greenter\Model\Sale\Invoice;

interface BillRepositoryInterface
{
    public function sendBill(Invoice $invoice): DocumentResponse;

    public function getXml(Invoice $invoice): string;

    public function getPdf(Invoice $invoice): string;

    public function getStatus(Invoice $invoice): StatusCdrResult;
}
