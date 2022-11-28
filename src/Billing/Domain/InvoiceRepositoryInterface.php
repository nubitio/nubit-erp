<?php

namespace App\Billing\Domain;

use App\Billing\Model\Invoice\Invoice;
use App\Billing\Model\Response\DocumentResponse;

interface InvoiceRepositoryInterface
{
    function createInvoice(Invoice $invoice): DocumentResponse;
}