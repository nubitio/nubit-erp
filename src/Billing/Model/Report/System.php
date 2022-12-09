<?php

namespace App\Billing\Model\Report;

use App\Domain\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
class System
{
    public function __construct(
        public readonly ?string $logo = null,
        public readonly ?string $hash = null,
    )
    {
    }
}