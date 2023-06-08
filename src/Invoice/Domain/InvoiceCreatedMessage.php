<?php

namespace App\Invoice\Domain;

use App\Core\Domain\Common\AsyncMessageInterface;

readonly class InvoiceCreatedMessage implements AsyncMessageInterface
{
    public function __construct(
        public Invoice $invoice
    ) {
    }
}
