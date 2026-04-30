<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Unit\Billing\Feature;

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

    public function testActiveOverrideTakesPriorityOverPlanFeature(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(1);
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        // Override is active and enables a feature that is disabled in the plan.
        $this->overrideRepository
            ->method('findByTenantAndKey')
            ->with(1, 'api_access')
            ->willReturn($this->makeOverride(1, 'api_access', true, active: true));

        // planFeatureRepository must NOT be consulted when override is active.
        $this->planFeatureRepository->expects(self::never())->method('findOneByPlanAndKey');

        self::assertTrue($this->makeChecker()->hasFeature('api_access'));
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
    // AC-8: No tenant in context → false (fail-closed)
    // -------------------------------------------------------------------------

    public function testHasFeatureReturnsFalseWithoutTenantContext(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(null);

        self::assertFalse($this->makeChecker()->hasFeature('excel_export'));
    }

    // -------------------------------------------------------------------------
    // AC-9: Plan not found → false (fail-closed)
    // -------------------------------------------------------------------------

    public function testHasFeatureReturnsFalseForUnknownPlan(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(42);
        $this->tenantContext->method('getTenantName')->willReturn('unknown-tenant');
        $this->overrideRepository->method('findByTenantAndKey')->willReturn(null);
        // TenantManager returns null → no control-plane record → fail closed.
        $this->tenantManager->method('getTenantByName')->with('unknown-tenant')->willReturn(null);

        self::assertFalse($this->makeChecker()->hasFeature('excel_export'));
    }

    // -------------------------------------------------------------------------
    // AC-15: getFeatureConfig('retention_days') → ['days' => 365]
    // -------------------------------------------------------------------------

    public function testGetFeatureConfigReturnsConfigFromPlanFeature(): void
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

    // -------------------------------------------------------------------------
    // requireFeature: throws FeatureNotAvailableException (HTTP 402)
    // -------------------------------------------------------------------------

    public function testRequireFeatureThrowsFeatureNotAvailableException(): void
    {
        $this->tenantContext->method('getTenantId')->willReturn(null);

        $this->expectException(FeatureNotAvailableException::class);
        $this->expectExceptionCode(402);

        $this->makeChecker()->requireFeature('excel_export');
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
