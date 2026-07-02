<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Service;

final readonly class ProvisionCommandRequest
{
    public function __construct(
        public string $tenant,
        public string $tenantDisplayName = '',
        public string $domain = '',
        public string $adminUser = 'admin',
        public int $provisionId = 0,
        public bool $createUser = false,
        public bool $setup = false,
        public string $adminPassword = '',
        public string $tenantDbPassword = '',
        public string $provisioningUrl = '',
        public string $companyRuc = '20000000001',
        public string $companyAddress = 'Pendiente por completar',
        public string $companyPhone = '999999999',
        public string $branchName = 'Principal',
        public string $branchAddress = 'Pendiente por completar',
        public string $branchPhone = '999999999',
    ) {}
}
