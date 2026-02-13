<?php

declare(strict_types=1);

namespace App\Onboarding\Service;

use App\Core\Service\ProvisionCommandRequest;
use App\Core\Service\ProvisionCommandRunner;

final readonly class TenantProvisioningService
{
    public function __construct(
        private ProvisionCommandRunner $commandRunner,
    ) {
    }

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
        ));
    }
}
