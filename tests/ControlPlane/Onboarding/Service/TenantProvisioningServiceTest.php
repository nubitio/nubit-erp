<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Onboarding\Service;

use App\ControlPlane\Onboarding\Service\TenantProvisioningService;
use App\ControlPlane\Tenant\Service\ProvisionCommandRequest;
use App\ControlPlane\Tenant\Service\ProvisionCommandRunnerInterface;
use PHPUnit\Framework\TestCase;

final class TenantProvisioningServiceTest extends TestCase
{
    public function testForwardsTheVerticalOntoTheProvisionRequest(): void
    {
        $captured = $this->runProvisioning('pharmacy');

        self::assertSame('pharmacy', $captured->vertical);
        self::assertSame('acme', $captured->tenant);
        self::assertTrue($captured->setup);
    }

    public function testVerticalDefaultsToEmptyWhenNotGiven(): void
    {
        self::assertSame('', $this->runProvisioning(null)->vertical);
    }

    private function runProvisioning(?string $vertical): ProvisionCommandRequest
    {
        $captured = null;
        $runner = $this->createMock(ProvisionCommandRunnerInterface::class);
        $runner
            ->method('run')
            ->willReturnCallback(static function (ProvisionCommandRequest $request) use (&$captured): array {
                $captured = $request;

                return ['successful' => true, 'exit_code' => 0, 'output' => ''];
            });

        $args = [
            'provisionId' => 1,
            'tenantName' => 'Acme',
            'subdomain' => 'acme',
            'domain' => 'acme.example',
            'adminPassword' => '',
            'tenantDbPassword' => '',
        ];
        if ($vertical !== null) {
            $args['vertical'] = $vertical;
        }

        new TenantProvisioningService($runner)->run(...$args);

        self::assertInstanceOf(ProvisionCommandRequest::class, $captured);

        return $captured;
    }
}
