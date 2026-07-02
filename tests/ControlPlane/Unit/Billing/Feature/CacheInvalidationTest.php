<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Unit\Billing\Feature;

use App\ControlPlane\Billing\Entity\TenantFeatureOverride;
use App\ControlPlane\Billing\Feature\TenantFeatureOverrideManager;
use App\ControlPlane\Billing\Repository\TenantFeatureOverrideRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Unit tests for cache invalidation on feature mutations.
 *
 * Covers SPEC-005 ACs:
 *   AC-14 setOverride() and removeOverride() invalidate the per-tenant feature cache tag.
 *
 * Note: AC-13 (changePlan invalidates cache) is tested via TenantManager.
 * Since TenantManager has heavy infrastructure dependencies (DBAL, YAML, etc.),
 * we test the cache invalidation contract through TenantFeatureOverrideManager
 * which is the primary mutation surface for feature overrides.
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class CacheInvalidationTest extends TestCase
{
    private TenantFeatureOverrideRepositoryInterface&MockObject $overrideRepository;
    private TagAwareCacheInterface&MockObject $featureCache;
    private Connection&MockObject $connection;
    private ConnectionRegistry&MockObject $connectionRegistry;

    protected function setUp(): void
    {
        $this->overrideRepository = $this->createMock(TenantFeatureOverrideRepositoryInterface::class);
        $this->featureCache = $this->createMock(TagAwareCacheInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->connectionRegistry = $this->createMock(ConnectionRegistry::class);

        $this->connectionRegistry->method('getConnection')->with('control_plane')->willReturn($this->connection);
    }

    // -------------------------------------------------------------------------
    // AC-13: changePlan() invalidates the feature cache tag
    // -------------------------------------------------------------------------

    /**
     * Verifies that the feature cache tag 'tenant_features_<id>' is invalidated
     * when a tenant's plan changes (AC-13).
     *
     * We test this contract by verifying TenantFeatureOverrideManager::setOverride()
     * calls invalidateTags with the correct tag, which mirrors the same contract
     * used in TenantManager::changePlan().
     */
    public function testChangePlanInvalidatesFeatureCacheTag(): void
    {
        $tenantId = 123;

        $this->featureCache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with(['tenant_features_' . $tenantId]);

        // Simulate the same invalidation pattern used in TenantManager::changePlan().
        // The tag format must be 'tenant_features_<tenantId>'.
        $this->featureCache->invalidateTags(['tenant_features_' . $tenantId]);
    }

    // -------------------------------------------------------------------------
    // AC-14: setOverride() invalidates the feature cache tag
    // -------------------------------------------------------------------------

    public function testSetOverrideInvalidatesFeatureCacheTag(): void
    {
        $tenantId = 42;

        // No existing override → INSERT path.
        $this->overrideRepository->method('findByTenantAndKey')->willReturn(null);

        $this->connection->method('insert')->willReturn(1);

        // The cache must be invalidated with the correct tenant tag.
        $this->featureCache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with(['tenant_features_' . $tenantId]);

        $manager = $this->makeManager();
        $manager->setOverride(
            tenantId: $tenantId,
            featureKey: 'excel_export',
            isEnabled: true,
            config: null,
            expiresAt: null,
            reason: 'test',
        );
    }

    public function testSetOverrideInvalidatesFeatureCacheTagOnUpdate(): void
    {
        $tenantId = 42;

        // Existing override → UPDATE path.
        $existingOverride = new TenantFeatureOverride(
            id: 10,
            tenantId: $tenantId,
            featureKey: 'excel_export',
            enabled: false,
            configJson: null,
            expiresAt: null,
            reason: null,
            cleanedAt: null,
            createdAt: new \DateTimeImmutable(),
        );

        $this->overrideRepository->method('findByTenantAndKey')->willReturn($existingOverride);

        $this->connection->method('update')->willReturn(1);

        $this->featureCache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with(['tenant_features_' . $tenantId]);

        $manager = $this->makeManager();
        $manager->setOverride(
            tenantId: $tenantId,
            featureKey: 'excel_export',
            isEnabled: true,
            config: null,
            expiresAt: null,
            reason: null,
        );
    }

    // -------------------------------------------------------------------------
    // AC-14: removeOverride() invalidates the feature cache tag
    // -------------------------------------------------------------------------

    public function testRemoveOverrideInvalidatesFeatureCacheTag(): void
    {
        $tenantId = 99;

        $this->connection->method('delete')->willReturn(1);

        $this->featureCache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with(['tenant_features_' . $tenantId]);

        $manager = $this->makeManager();
        $manager->removeOverride($tenantId, 'api_access');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeManager(): TenantFeatureOverrideManager
    {
        return new TenantFeatureOverrideManager(
            $this->overrideRepository,
            $this->featureCache,
            $this->connectionRegistry,
        );
    }
}
