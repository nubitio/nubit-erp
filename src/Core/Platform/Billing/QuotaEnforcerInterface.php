<?php

declare(strict_types=1);

namespace App\Core\Platform\Billing;

interface QuotaEnforcerInterface extends \Nubit\Platform\Quota\Contract\QuotaEnforcerInterface
{
    public function enforce(string $resource): void;

    public function releaseLocks(): void;
}
