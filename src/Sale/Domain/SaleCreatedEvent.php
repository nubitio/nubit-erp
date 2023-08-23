<?php

namespace App\Sale\Domain;

use Symfony\Contracts\EventDispatcher\Event;

class SaleCreatedEvent extends Event
{
    public const NAME = 'sale.created';

    public function __construct(
        public readonly Sale $sale
    ) {
    }
}
