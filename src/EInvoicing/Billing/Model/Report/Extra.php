<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Model\Report;

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
