<?php

declare(strict_types=1);

namespace App\Tests\Settings\State;

use ApiPlatform\Metadata\Patch;
use App\Settings\Entity\TenantSettings;
use App\Settings\Service\TenantSettingsService;
use App\Settings\State\TenantSettingsPatchProcessor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[AllowMockObjectsWithoutExpectations]
final class TenantSettingsPatchProcessorTest extends TestCase
{
    public function testProcessUpdatesExistingSingletonInsteadOfSavingIncomingEntity(): void
    {
        $existing = new TenantSettings()->setIgv(18.0);
        $incoming = new TenantSettings()->setIgv(10.5);

        /** @var TenantSettingsService&MockObject $service */
        $service = $this->createMock(TenantSettingsService::class);
        $service->method('get')->willReturn($existing);
        $service->expects(self::once())->method('save')->with(self::identicalTo($existing));

        $requestStack = new RequestStack();
        $requestStack->push(new Request(content: '{"igv":10.5}'));

        $processor = new TenantSettingsPatchProcessor($service, $requestStack);

        $result = $processor->process($incoming, new Patch());

        self::assertSame($existing, $result);
        self::assertSame(10.5, $existing->getIgv());
    }
}
