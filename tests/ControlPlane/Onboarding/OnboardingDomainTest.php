<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Onboarding;

use App\ControlPlane\Onboarding\Message\ProvisionTenantMessage;
use App\ControlPlane\Onboarding\Service\TenantProvisioningService;
use App\ControlPlane\Tenant\Service\ProvisionCommandRunnerInterface;
use PHPUnit\Framework\TestCase;

final class OnboardingDomainTest extends TestCase
{
    // ── ProvisionTenantMessage ────────────────────────────────────────────────

    public function testProvisionTenantMessageHoldsFields(): void
    {
        $msg = new ProvisionTenantMessage(
            provisionId: 42,
            accountId: 7,
            tenantName: 'Empresa Demo',
            subdomain: 'demo',
            domain: 'demo.efact.app',
            adminPasswordSecret: 'secret-ref-abc',
        );

        self::assertSame(42, $msg->provisionId);
        self::assertSame(7, $msg->accountId);
        self::assertSame('Empresa Demo', $msg->tenantName);
        self::assertSame('demo', $msg->subdomain);
        self::assertSame('demo.efact.app', $msg->domain);
        self::assertSame('secret-ref-abc', $msg->adminPasswordSecret);
    }

    // ── TenantProvisioningService ─────────────────────────────────────────────

    public function testTenantProvisioningServiceDelegatesToCommandRunner(): void
    {
        $expected = ['successful' => true, 'exit_code' => 0, 'output' => 'Done'];

        $runner = $this->createMock(ProvisionCommandRunnerInterface::class);
        $runner->expects(self::once())
            ->method('run')
            ->willReturn($expected);

        $service = new TenantProvisioningService($runner);
        $result = $service->run(
            provisionId: 1,
            tenantName: 'Demo',
            subdomain: 'demo',
            domain: 'demo.efact.app',
            adminPassword: 'pass123',
            tenantDbPassword: 'db-pass',
        );

        self::assertSame($expected, $result);
    }

    public function testTenantProvisioningServiceReturnsRunnerOutputOnFailure(): void
    {
        $expected = ['successful' => false, 'exit_code' => 1, 'output' => 'Error: DB exists'];

        $runner = $this->createStub(ProvisionCommandRunnerInterface::class);
        $runner->method('run')->willReturn($expected);

        $service = new TenantProvisioningService($runner);
        $result = $service->run(1, 'Demo', 'demo', 'demo.efact.app', 'pass', 'dbpass');

        self::assertFalse($result['successful']);
        self::assertSame(1, $result['exit_code']);
    }
}
