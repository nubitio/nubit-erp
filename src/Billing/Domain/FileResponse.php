<?php

namespace App\Billing\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
readonly class FileResponse
{
    public function __construct(
        public string $file,
        public string $name,
    ) {
    }
}
