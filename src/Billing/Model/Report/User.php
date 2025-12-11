<?php

declare(strict_types=1);

namespace App\Billing\Model\Report;

use App\Core\Annotation\IgnoreAutowire;

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
