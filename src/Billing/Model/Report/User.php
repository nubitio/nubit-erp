<?php

namespace App\Billing\Model\Report;

use App\Domain\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
class User
{
    public function __construct(
        public readonly ?string $header = null,
        /** @var Extra[] */
        public readonly ?array  $extras = null,
    )
    {
    }
}