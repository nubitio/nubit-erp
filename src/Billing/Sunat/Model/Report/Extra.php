<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Model\Report;

use App\Efact\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
readonly class Extra
{
    public function __construct(
        public string $name,
        public string $value,
    ) {}
}
