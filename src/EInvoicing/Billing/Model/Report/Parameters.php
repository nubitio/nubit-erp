<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Model\Report;

use App\Core\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
readonly class Parameters
{
    public function __construct(
        public ?System $system = null,
        public ?User $user = null,
    ) {
    }
}
