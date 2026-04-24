<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Port;

final readonly class InitialTenantSetupRequest
{
    public function __construct(
        public string $adminUsername,
        public string $adminPassword,
        public string $companyRuc,
        public string $companyBusinessName,
        public ?string $companyTradeName,
        public string $companyFiscalAddress,
        public ?string $companyPhone,
        public string $branchName,
        public ?string $branchAddress,
        public ?string $branchPhone,
    ) {
    }
}
