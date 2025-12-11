<?php

declare(strict_types=1);

namespace App\Billing\Domain;

use App\Core\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
readonly class FileResponse
{
    public function __construct(
        public string $file,
        public string $name,
    ) {
    }
}
