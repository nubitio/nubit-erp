<?php

namespace App\Billing\Model\Report;

use App\Core\Domain\Annotation\IgnoreAutowire;

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
