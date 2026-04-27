<?php

declare(strict_types=1);

namespace App\Core\Platform\Tenant;

interface TenantConnectionSwitcherInterface extends \Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface
{
    public function switchConnection(string $tenant): void;
}
