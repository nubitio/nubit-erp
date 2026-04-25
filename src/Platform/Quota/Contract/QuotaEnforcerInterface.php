<?php

declare(strict_types=1);

namespace App\Platform\Quota\Contract;

interface QuotaEnforcerInterface
{
    public function enforce(string $resource): void;

    public function releaseLocks(): void;
}
