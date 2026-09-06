<?php

declare(strict_types=1);

namespace App\ControlPlane\Onboarding\Service;

use App\ControlPlane\Tenant\Service\ProvisionCommandRequest;
use App\ControlPlane\Tenant\Service\ProvisionCommandRunnerInterface;

final readonly class TenantProvisioningService
{
    public function __construct(
        private ProvisionCommandRunnerInterface $commandRunner,
    ) {}

    /**
     * @return array{successful:bool,exit_code:int,output:string}
     */
    public function run(
        int $provisionId,
        string $tenantName,
        string $subdomain,
        string $domain,
        string $adminPassword,
        string $tenantDbPassword,
        string $vertical = '',
    ): array {
        return $this->commandRunner->run(new ProvisionCommandRequest(
            tenant: $subdomain,
            tenantDisplayName: $tenantName,
            domain: $domain,
            adminUser: 'admin',
            provisionId: $provisionId,
            createUser: true,
            setup: true,
            adminPassword: $adminPassword,
            tenantDbPassword: $tenantDbPassword,
            vertical: $vertical,
        ));
    }
}
