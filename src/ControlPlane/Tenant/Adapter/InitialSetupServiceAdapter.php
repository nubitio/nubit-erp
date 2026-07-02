<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Adapter;

use App\ControlPlane\Tenant\Port\InitialTenantSetupPortInterface;
use App\ControlPlane\Tenant\Port\InitialTenantSetupRequest;
use App\Setup\Dto\InitialSetupInput;
use App\Setup\InitialSetupService;

final readonly class InitialSetupServiceAdapter implements InitialTenantSetupPortInterface
{
    public function __construct(
        private InitialSetupService $initialSetupService,
    ) {}

    public function configure(InitialTenantSetupRequest $request): void
    {
        $input = new InitialSetupInput();
        $input->adminUsername = $request->adminUsername;
        $input->adminPassword = $request->adminPassword;
        $input->companyRuc = $request->companyRuc;
        $input->companyBusinessName = $request->companyBusinessName;
        $input->companyTradeName = $request->companyTradeName;
        $input->companyFiscalAddress = $request->companyFiscalAddress;
        $input->companyPhone = $request->companyPhone;
        $input->branchName = $request->branchName;
        $input->branchAddress = $request->branchAddress;
        $input->branchPhone = $request->branchPhone;

        $this->initialSetupService->configure($input);
    }
}
