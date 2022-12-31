<?php

namespace App\Billing\Model\Report;

use App\Core\Domain\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
class Parameters
{
    public function __construct(
        public readonly ?System $system = null,
        public readonly ?User $user = null,
    ) {
    }
}
