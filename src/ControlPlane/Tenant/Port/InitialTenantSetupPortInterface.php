<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Port;

interface InitialTenantSetupPortInterface
{
    public function configure(InitialTenantSetupRequest $request): void;
}
