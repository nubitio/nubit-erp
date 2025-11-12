<?php

namespace App\Billing\Model\Report;

use App\Core\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
readonly class Extra
{
    public function __construct(
        public string $name,
        public string $value,
    ) {
    }
}
