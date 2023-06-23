<?php

namespace App\Billing\Model\Report;

use App\Core\Domain\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
readonly class Extra
{
    public function __construct(
        public string $name,
        public string $value,
    ) {
    }
}
