<?php

declare(strict_types=1);

namespace App\Tests\State\Purchase;

use App\Efact\Service\SettingsService;
use App\Efact\Config\EfactConstants as Constants;
use App\Purchasing\State\PurchasesWriteMode;
use App\Purchasing\State\PurchasesWritePolicy;
use App\Purchasing\State\PurchasesWritePolicyResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PurchasesWritePolicyResolverTest extends TestCase
{
    private SettingsService&MockObject $settings;
    private PurchasesWritePolicyResolver $resolver;

    protected function setUp(): void
    {
        $this->settings = $this->createMock(SettingsService::class);
        $this->resolver = new PurchasesWritePolicyResolver($this->settings);
    }

    public function testLegacyModeReturnsNonWritingPolicy(): void
    {
        $this->settings->method('get')
            ->with(Constants::PURCHASES_V2_MODE, PurchasesWriteMode::DUAL_WRITE)
            ->willReturn('legacy');

        $policy = $this->resolver->resolve();

        self::assertInstanceOf(PurchasesWritePolicy::class, $policy);
        self::assertSame(PurchasesWriteMode::LEGACY, $policy->mode);
        self::assertFalse($policy->writeNewSchema);
        self::assertFalse($policy->skipLegacyPersist);
        self::assertFalse($policy->strict);
    }

    public function testV2AuthoritativeModeSkipsLegacyPersist(): void
    {
        $this->settings->method('get')
            ->with(Constants::PURCHASES_V2_MODE, PurchasesWriteMode::DUAL_WRITE)
            ->willReturn('v2_authoritative');

        $policy = $this->resolver->resolve();

        self::assertSame(PurchasesWriteMode::AUTHORITATIVE, $policy->mode);
        self::assertTrue($policy->writeNewSchema);
        self::assertTrue($policy->skipLegacyPersist);
        self::assertTrue($policy->strict);
    }

    public function testDualWriteModeAlwaysKeepsLegacyPersist(): void
    {
        $this->settings->method('get')
            ->willReturnMap([
                [Constants::PURCHASES_V2_MODE, PurchasesWriteMode::DUAL_WRITE, 'dual_write'],
                [Constants::PURCHASES_V2_DUAL_WRITE_ENABLED, 'true', 'true'],
                [Constants::PURCHASES_V2_DUAL_WRITE_STRICT, 'false', 'false'],
            ]);

        $policy = $this->resolver->resolve();

        self::assertSame(PurchasesWriteMode::DUAL_WRITE, $policy->mode);
        self::assertTrue($policy->writeNewSchema);
        self::assertFalse($policy->skipLegacyPersist);
        self::assertFalse($policy->strict);
    }

    public function testDualWriteWithV2DisabledSkipsNewSchemaWrite(): void
    {
        $this->settings->method('get')
            ->willReturnMap([
                [Constants::PURCHASES_V2_MODE, PurchasesWriteMode::DUAL_WRITE, 'dual_write'],
                [Constants::PURCHASES_V2_DUAL_WRITE_ENABLED, 'true', 'false'],
                [Constants::PURCHASES_V2_DUAL_WRITE_STRICT, 'false', 'false'],
            ]);

        $policy = $this->resolver->resolve();

        self::assertFalse($policy->writeNewSchema);
        self::assertFalse($policy->skipLegacyPersist);
    }

    public function testDualWriteWithStrictFlagEnabled(): void
    {
        $this->settings->method('get')
            ->willReturnMap([
                [Constants::PURCHASES_V2_MODE, PurchasesWriteMode::DUAL_WRITE, 'dual_write'],
                [Constants::PURCHASES_V2_DUAL_WRITE_ENABLED, 'true', 'true'],
                [Constants::PURCHASES_V2_DUAL_WRITE_STRICT, 'false', 'true'],
            ]);

        $policy = $this->resolver->resolve();

        self::assertTrue($policy->writeNewSchema);
        self::assertFalse($policy->skipLegacyPersist);
        self::assertTrue($policy->strict);
    }

    public function testUnknownModeDefaultsToDualWrite(): void
    {
        $this->settings->method('get')
            ->willReturnMap([
                [Constants::PURCHASES_V2_MODE, PurchasesWriteMode::DUAL_WRITE, 'garbage_value'],
                [Constants::PURCHASES_V2_DUAL_WRITE_ENABLED, 'true', 'true'],
                [Constants::PURCHASES_V2_DUAL_WRITE_STRICT, 'false', 'false'],
            ]);

        $policy = $this->resolver->resolve();

        self::assertSame(PurchasesWriteMode::DUAL_WRITE, $policy->mode);
        self::assertFalse($policy->skipLegacyPersist);
    }
}
