<?php

declare(strict_types=1);

namespace App\Core\Platform\Billing;

interface QuotaEnforcerInterface
{
    public function enforce(string $resource): void;

    public function releaseLocks(): void;
}
