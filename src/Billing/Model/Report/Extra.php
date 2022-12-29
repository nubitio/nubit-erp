<?php

namespace App\Billing\Model\Report;

use App\Domain\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
class Extra
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {
    }
}
