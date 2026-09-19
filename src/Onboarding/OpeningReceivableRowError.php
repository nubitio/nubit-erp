<?php

declare(strict_types=1);

namespace App\Onboarding;

final readonly class OpeningReceivableRowError
{
    public function __construct(
        public string $message,
    ) {}
}
