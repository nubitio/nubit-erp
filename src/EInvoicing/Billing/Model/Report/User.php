<?php

declare(strict_types=1);

namespace App\EInvoicing\Billing\Model\Report;

use App\Efact\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
readonly class User
{
    public function __construct(
        public ?string $header = null,
        /** @var Extra[] */
        public ?array $extras = null,
    ) {
    }
}
