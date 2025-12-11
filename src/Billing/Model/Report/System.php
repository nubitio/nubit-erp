<?php

declare(strict_types=1);

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
