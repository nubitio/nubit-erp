<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Model\Report;

use App\Efact\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
readonly class System
{
    public function __construct(
        public ?string $logo = null,
        public ?string $hash = null,
    ) {}
}
