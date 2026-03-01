<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\State\Sale\SalesWriteMode;
use App\State\Sale\SalesWritePolicy;
use App\State\Sale\SalesWritePolicyResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SalesWritePolicyResolverTest extends TestCase
{
    private SettingsService&MockObject $settings;
    private SalesWritePolicyResolver $resolver;

    protected function setUp(): void
    {
        $this->settings = $this->createMock(SettingsService::class);
        $this->resolver = new SalesWritePolicyResolver($this->settings);
    }

    public function testLegacyModeReturnsNonWritingPolicy(): void
    {
        $this->settings->method('get')
            ->with(Constants::SALES_V2_MODE, SalesWriteMode::DUAL_WRITE)
            ->willReturn('legacy');

        $policy = $this->resolver->resolve();

        self::assertInstanceOf(SalesWritePolicy::class, $policy);
        self::assertSame(SalesWriteMode::LEGACY, $policy->mode);
        self::assertFalse($policy->writeNewSchema);
        self::assertFalse($policy->skipLegacyPersist);
        self::assertFalse($policy->strict);
    }

    public function testV2AuthoritativeModeSkipsLegacyPersist(): void
    {
        $this->settings->method('get')
            ->with(Constants::SALES_V2_MODE, SalesWriteMode::DUAL_WRITE)
            ->willReturn('v2_authoritative');

        $policy = $this->resolver->resolve();

        self::assertSame(SalesWriteMode::AUTHORITATIVE, $policy->mode);
        self::assertTrue($policy->writeNewSchema);
        self::assertTrue($policy->skipLegacyPersist);
        self::assertTrue($policy->strict);
    }

    public function testDualWriteModeAlwaysKeepsLegacyPersist(): void
    {
        $this->settings->method('get')
            ->willReturnMap([
                [Constants::SALES_V2_MODE, SalesWriteMode::DUAL_WRITE, 'dual_write'],
                [Constants::SALES_V2_DUAL_WRITE_ENABLED, 'true', 'true'],
                [Constants::SALES_V2_DUAL_WRITE_STRICT, 'false', 'false'],
            ]);

        $policy = $this->resolver->resolve();

        self::assertSame(SalesWriteMode::DUAL_WRITE, $policy->mode);
        self::assertTrue($policy->writeNewSchema);
        self::assertFalse($policy->skipLegacyPersist);
        self::assertFalse($policy->strict);
    }

    public function testDualWriteWithV2DisabledSkipsNewSchemaWrite(): void
    {
        $this->settings->method('get')
            ->willReturnMap([
                [Constants::SALES_V2_MODE, SalesWriteMode::DUAL_WRITE, 'dual_write'],
                [Constants::SALES_V2_DUAL_WRITE_ENABLED, 'true', 'false'],
                [Constants::SALES_V2_DUAL_WRITE_STRICT, 'false', 'false'],
            ]);

        $policy = $this->resolver->resolve();

        self::assertFalse($policy->writeNewSchema);
        self::assertFalse($policy->skipLegacyPersist);
    }

    public function testDualWriteWithStrictFlagEnabled(): void
    {
        $this->settings->method('get')
            ->willReturnMap([
                [Constants::SALES_V2_MODE, SalesWriteMode::DUAL_WRITE, 'dual_write'],
                [Constants::SALES_V2_DUAL_WRITE_ENABLED, 'true', 'true'],
                [Constants::SALES_V2_DUAL_WRITE_STRICT, 'false', 'true'],
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
                [Constants::SALES_V2_MODE, SalesWriteMode::DUAL_WRITE, 'unknown_value'],
                [Constants::SALES_V2_DUAL_WRITE_ENABLED, 'true', 'true'],
                [Constants::SALES_V2_DUAL_WRITE_STRICT, 'false', 'false'],
            ]);

        $policy = $this->resolver->resolve();

        self::assertSame(SalesWriteMode::DUAL_WRITE, $policy->mode);
        self::assertFalse($policy->skipLegacyPersist);
    }
}
