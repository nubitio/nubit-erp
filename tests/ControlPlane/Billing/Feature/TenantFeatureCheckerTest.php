<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Billing\Feature;

use App\ControlPlane\Billing\Entity\PlanFeature;
use App\ControlPlane\Billing\Entity\TenantFeatureOverride;
use App\ControlPlane\Billing\Feature\TenantFeatureChecker;
use App\ControlPlane\Billing\Repository\PlanFeatureRepositoryInterface;
use App\ControlPlane\Billing\Repository\TenantFeatureOverrideRepositoryInterface;
use App\ControlPlane\Tenant\Service\TenantManager;
use Nubit\Platform\Feature\Exception\FeatureNotAvailableException;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Unit tests for TenantFeatureChecker.
 *
 * Covers SPEC-005 ACs:
 *   AC-4  hasFeature('excel_export') → true  (plan basic, feature enabled)
 *   AC-5  hasFeature('api_access')   → false (plan basic, feature disabled)
 *   AC-6  Active override wins over plan_features
 *   AC-7  Expired override is ignored; plan_features used instead
 *   AC-8  No tenant in context → false (fail-closed)
 *   AC-9  Plan not found → false (fail-closed)
 *   AC-15 getFeatureConfig('retention_days') → ['days' => 365]
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class TenantFeatureCheckerTest extends TestCase
{
    private TenantContext&MockObject $tenantContext;
    private TenantManager&MockObject $tenantManager;
    private PlanFeatureRepositoryInterface&MockObject $planFeatureRepository;
    private TenantFeatureOverrideRepositoryInterface&MockObject $overrideRepository;
    private TagAwareCacheInterface&MockObject $featureCache;

    protected function setUp(): void
    {
        $this->tenantContext         = $this->createMock(TenantContext::class);
        $this->tenantManager         = $this->createMock(TenantManager::class);
        $this->planFeatureRepository = $this->createMock(PlanFeatureRepositoryInterface::class);
        $this->overrideRepository    = $this->createMock(TenantFeatureOverrideRepositoryInterface::class);
        $this->featureCache          = $this->createMock(TagAwareCacheInterface::class);

        // By default the cache is transparent (executes the callback immediately).
        $this->featureCache
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);

                return $callback($item);
            });
    }

    // -------------------------------------------------------------------------
    // AC-8: No tenant in context → false (fail-closed)
    // -------------------------------------------------------------------------

    public function testHasFeatureReturnsFalseWhenNoTenantInContext(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(null);

        self::assertFalse($this->makeChecker()->hasFeature('excel_export'));
    }

    public function testGetFeatureConfigReturnsEmptyArrayWhenNoTenantInContext(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(null);

        self::assertSame([], $this->makeChecker()->getFeatureConfig('excel_export'));
    }

    public function testGetEntitlementsReturnsEmptyArrayWhenNoTenantInContext(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(null);

        self::assertSame([], $this->makeChecker()->getEntitlements());
    }

    // -------------------------------------------------------------------------
    // AC-9: Plan not found → false (fail-closed)
    // -------------------------------------------------------------------------

    public function testHasFeatureReturnsFalseWhenTenantNotFoundInControlPlane(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(42);
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->overrideRepository->method('findByTenantAndKey')->willReturn(null);
        $this->tenantManager->method('getTenantByName')->with('acme')->willReturn(null);

        self::assertFalse($this->makeChecker()->hasFeature('excel_export'));
    }

    public function testHasFeatureReturnsFalseWhenPlanIsEmpty(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(42);
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->overrideRepository->method('findByTenantAndKey')->willReturn(null);
        $this->tenantManager->method('getTenantByName')->willReturn(['id' => 42, 'name' => 'acme', 'plan' => '']);

        self::assertFalse($this->makeChecker()->hasFeature('excel_export'));
    }

    // -------------------------------------------------------------------------
    // AC-4: hasFeature('excel_export') → true for plan basic
    // -------------------------------------------------------------------------

    public function testHasFeatureReturnsTrueForEnabledPlanFeature(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(1);
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->overrideRepository->method('findByTenantAndKey')->willReturn(null);
        $this->tenantManager->method('getTenantByName')->willReturn(['id' => 1, 'name' => 'acme', 'plan' => 'basic']);
        $this->planFeatureRepository
            ->method('findOneByPlanAndKey')
            ->with('basic', 'excel_export')
            ->willReturn($this->makePlanFeature('basic', 'excel_export', true));

        self::assertTrue($this->makeChecker()->hasFeature('excel_export'));
    }

    // -------------------------------------------------------------------------
    // AC-5: hasFeature('api_access') → false for plan basic (disabled)
    // -------------------------------------------------------------------------

    public function testHasFeatureReturnsFalseForDisabledPlanFeature(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(1);
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->overrideRepository->method('findByTenantAndKey')->willReturn(null);
        $this->tenantManager->method('getTenantByName')->willReturn(['id' => 1, 'name' => 'acme', 'plan' => 'basic']);
        $this->planFeatureRepository
            ->method('findOneByPlanAndKey')
            ->with('basic', 'api_access')
            ->willReturn($this->makePlanFeature('basic', 'api_access', false));

        self::assertFalse($this->makeChecker()->hasFeature('api_access'));
    }

    // -------------------------------------------------------------------------
    // AC-6: Active override wins over plan_features
    // -------------------------------------------------------------------------

    public function testActiveOverrideEnablesFeatureDisabledInPlan(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(1);
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->overrideRepository
            ->method('findByTenantAndKey')
            ->with(1, 'api_access')
            ->willReturn($this->makeOverride(1, 'api_access', true, active: true));

        // planFeatureRepository should NOT be consulted when override is active.
        $this->planFeatureRepository->expects(self::never())->method('findOneByPlanAndKey');

        self::assertTrue($this->makeChecker()->hasFeature('api_access'));
    }

    public function testActiveOverrideDisablesFeatureEnabledInPlan(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(1);
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->overrideRepository
            ->method('findByTenantAndKey')
            ->with(1, 'excel_export')
            ->willReturn($this->makeOverride(1, 'excel_export', false, active: true));

        $this->planFeatureRepository->expects(self::never())->method('findOneByPlanAndKey');

        self::assertFalse($this->makeChecker()->hasFeature('excel_export'));
    }

    // -------------------------------------------------------------------------
    // AC-7: Expired override is ignored; plan_features used instead
    // -------------------------------------------------------------------------

    public function testExpiredOverrideIsIgnoredAndPlanFeatureIsUsed(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(1);
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        // Override exists but is expired (active: false) and says disabled.
        $this->overrideRepository
            ->method('findByTenantAndKey')
            ->willReturn($this->makeOverride(1, 'excel_export', false, active: false));
        $this->tenantManager->method('getTenantByName')->willReturn(['id' => 1, 'name' => 'acme', 'plan' => 'basic']);
        // Plan says enabled → should win because override is expired.
        $this->planFeatureRepository
            ->method('findOneByPlanAndKey')
            ->with('basic', 'excel_export')
            ->willReturn($this->makePlanFeature('basic', 'excel_export', true));

        self::assertTrue($this->makeChecker()->hasFeature('excel_export'));
    }

    // -------------------------------------------------------------------------
    // AC-15: getFeatureConfig('retention_days') → ['days' => 365]
    // -------------------------------------------------------------------------

    public function testGetFeatureConfigReturnsPlanConfig(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(1);
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->overrideRepository->method('findByTenantAndKey')->willReturn(null);
        $this->tenantManager->method('getTenantByName')->willReturn(['id' => 1, 'name' => 'acme', 'plan' => 'basic']);
        $this->planFeatureRepository
            ->method('findOneByPlanAndKey')
            ->with('basic', 'retention_days')
            ->willReturn($this->makePlanFeature('basic', 'retention_days', true, ['days' => 365]));

        self::assertSame(['days' => 365], $this->makeChecker()->getFeatureConfig('retention_days'));
    }

    public function testGetFeatureConfigReturnsEmptyArrayForDisabledFeature(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(1);
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->overrideRepository->method('findByTenantAndKey')->willReturn(null);
        $this->tenantManager->method('getTenantByName')->willReturn(['id' => 1, 'name' => 'acme', 'plan' => 'basic']);
        $this->planFeatureRepository
            ->method('findOneByPlanAndKey')
            ->with('basic', 'retention_days')
            ->willReturn($this->makePlanFeature('basic', 'retention_days', false, ['days' => 365]));

        self::assertSame([], $this->makeChecker()->getFeatureConfig('retention_days'));
    }

    public function testGetFeatureConfigReturnsOverrideConfig(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(1);
        $this->overrideRepository
            ->method('findByTenantAndKey')
            ->willReturn($this->makeOverride(1, 'retention_days', true, active: true, config: ['days' => 730]));

        self::assertSame(['days' => 730], $this->makeChecker()->getFeatureConfig('retention_days'));
    }

    // -------------------------------------------------------------------------
    // requireFeature
    // -------------------------------------------------------------------------

    public function testRequireFeatureThrowsWhenFeatureNotAvailable(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(null);

        $this->expectException(FeatureNotAvailableException::class);
        $this->makeChecker()->requireFeature('excel_export');
    }

    public function testRequireFeatureDoesNotThrowWhenFeatureAvailable(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(1);
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->overrideRepository->method('findByTenantAndKey')->willReturn(null);
        $this->tenantManager->method('getTenantByName')->willReturn(['id' => 1, 'name' => 'acme', 'plan' => 'basic']);
        $this->planFeatureRepository
            ->method('findOneByPlanAndKey')
            ->willReturn($this->makePlanFeature('basic', 'excel_export', true));

        $this->makeChecker()->requireFeature('excel_export');
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // getEntitlements
    // -------------------------------------------------------------------------

    public function testGetEntitlementsMergesPlanFeaturesAndActiveOverrides(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(1);
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->tenantManager->method('getTenantByName')->willReturn(['id' => 1, 'name' => 'acme', 'plan' => 'basic']);
        $this->planFeatureRepository->method('findByPlan')->with('basic')->willReturn([
            $this->makePlanFeature('basic', 'excel_export', true),
            $this->makePlanFeature('basic', 'api_access', false),
        ]);
        // Active override enables api_access (overrides plan).
        $this->overrideRepository->method('findActiveByTenant')->with(1)->willReturn([
            $this->makeOverride(1, 'api_access', true, active: true),
        ]);

        $entitlements = $this->makeChecker()->getEntitlements();

        self::assertArrayHasKey('excel_export', $entitlements);
        self::assertTrue($entitlements['excel_export']['enabled']);

        self::assertArrayHasKey('api_access', $entitlements);
        self::assertTrue($entitlements['api_access']['enabled'], 'Active override should enable api_access');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeChecker(): TenantFeatureChecker
    {
        return new TenantFeatureChecker(
            $this->tenantContext,
            $this->tenantManager,
            $this->planFeatureRepository,
            $this->overrideRepository,
            $this->featureCache,
        );
    }

    /**
     * @param array<string, mixed>|null $config
     */
    private function makePlanFeature(
        string $planName,
        string $featureKey,
        bool $enabled,
        ?array $config = null,
    ): PlanFeature {
        return new PlanFeature(
            id: 1,
            planName: $planName,
            featureKey: $featureKey,
            enabled: $enabled,
            configJson: $config,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    /**
     * @param array<string, mixed>|null $config
     */
    private function makeOverride(
        int $tenantId,
        string $featureKey,
        bool $enabled,
        bool $active,
        ?array $config = null,
    ): TenantFeatureOverride {
        $expiresAt = $active
            ? null                              // no expiry → always active
            : new \DateTimeImmutable('-1 day'); // past → expired

        return new TenantFeatureOverride(
            id: 1,
            tenantId: $tenantId,
            featureKey: $featureKey,
            enabled: $enabled,
            configJson: $config,
            expiresAt: $expiresAt,
            reason: null,
            cleanedAt: null,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
