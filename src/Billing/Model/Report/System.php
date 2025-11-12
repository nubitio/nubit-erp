<?php

namespace App\Billing\Model\Report;

use App\Core\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
readonly class System
{
    public function __construct(
        public ?string $logo = null,
        public ?string $hash = null,
    ) {
    }
}
