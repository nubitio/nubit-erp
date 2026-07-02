<?php

declare(strict_types=1);

namespace App\Billing\Sunat\Domain;

use App\Efact\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
readonly class FileResponse
{
    public function __construct(
        public string $file,
        public string $name,
    ) {}
}
