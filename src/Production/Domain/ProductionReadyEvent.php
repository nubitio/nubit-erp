<?php

namespace App\Production\Domain;

use Symfony\Contracts\EventDispatcher\Event;

class ProductionReadyEvent extends Event
{
    public const NAME = 'production.ready';

    public function __construct(
        public readonly Production $production
    ) {
    }
}
