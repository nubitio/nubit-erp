<?php

namespace App\CashboxDetail\Domain\Event;

use App\CashboxDetail\Domain\CashboxDetail;
use Symfony\Contracts\EventDispatcher\Event;

class CashboxOperationEvent extends Event
{
    public function __construct(
        public readonly CashboxDetail $cashboxDetail,
    ) {
    }
}
