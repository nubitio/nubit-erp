<?php

namespace App\Billing\Model\Report;

use App\Core\Domain\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
readonly class Parameters
{
    public function __construct(
        public ?System $system = null,
        public ?User $user = null,
    ) {
    }
}
