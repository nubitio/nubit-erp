<?php

declare(strict_types=1);

namespace App\Treasury\Domain;

/** The customer, supplier or employee a movement is with. */
final readonly class Party
{
    public function __construct(
        public string $type,
        public string $id,
    ) {}
}
