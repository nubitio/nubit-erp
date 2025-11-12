<?php

namespace App\Billing\Model\Report;

use App\Core\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
readonly class Parameters
{
    public function __construct(
        public ?System $system = null,
        public ?User $user = null,
    ) {
    }
}
