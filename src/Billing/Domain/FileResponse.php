<?php

namespace App\Billing\Domain;

use App\Domain\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
class FileResponse
{
    public function __construct(
        public readonly string $file,
        public readonly string $name
    ) {
    }
}
