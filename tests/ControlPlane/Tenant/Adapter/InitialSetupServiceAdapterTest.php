<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Tenant\Adapter;

use App\ControlPlane\Tenant\Adapter\InitialSetupServiceAdapter;
use App\ControlPlane\Tenant\Port\InitialTenantSetupRequest;
use App\Dto\Setup\InitialSetupInput;
use App\Setup\InitialSetupService;
use PHPUnit\Framework\TestCase;

final class InitialSetupServiceAdapterTest extends TestCase
{
    public function testMapsControlPlaneRequestToSetupInput(): void
    {
        $setupService = $this->createMock(InitialSetupService::class);
        $setupService->expects(self::once())
            ->method('configure')
            ->with(self::callback(function (InitialSetupInput $input): bool {
                self::assertSame('admin', $input->adminUsername);
                self::assertSame('Admin.3000', $input->adminPassword);
                self::assertSame('20123456789', $input->companyRuc);
                self::assertSame('Empresa Demo SAC', $input->companyBusinessName);
                self::assertSame('Demo', $input->companyTradeName);
                self::assertSame('Av. Principal 123', $input->companyFiscalAddress);
                self::assertSame('999999999', $input->companyPhone);
                self::assertSame('Principal', $input->branchName);
                self::assertSame('Av. Principal 123', $input->branchAddress);
                self::assertSame('988888888', $input->branchPhone);

                return true;
            }));

        $adapter = new InitialSetupServiceAdapter($setupService);
        $adapter->configure(new InitialTenantSetupRequest(
            'admin',
            'Admin.3000',
            '20123456789',
            'Empresa Demo SAC',
            'Demo',
            'Av. Principal 123',
            '999999999',
            'Principal',
            'Av. Principal 123',
            '988888888',
        ));
    }
}
