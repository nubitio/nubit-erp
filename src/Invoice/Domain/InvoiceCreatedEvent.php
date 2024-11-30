<?php

namespace App\Invoice\Domain;

use Symfony\Contracts\EventDispatcher\Event;

class InvoiceCreatedEvent extends Event
{
    public const NAME = 'invoice.created';

    public function __construct(
        public readonly Invoice $invoice,
    ) {
    }
}
