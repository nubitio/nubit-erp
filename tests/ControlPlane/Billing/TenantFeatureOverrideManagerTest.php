<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Billing;

use App\ControlPlane\Billing\Entity\TenantFeatureOverride;
use App\ControlPlane\Billing\Feature\TenantFeatureOverrideManager;
use App\ControlPlane\Billing\Repository\TenantFeatureOverrideRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class TenantFeatureOverrideManagerTest extends TestCase
{
    private function makeManager(
        TenantFeatureOverrideRepositoryInterface $repo,
        Connection $connection,
        TagAwareCacheInterface $cache,
    ): TenantFeatureOverrideManager {
        $registry = $this->createStub(ConnectionRegistry::class);
        $registry->method('getConnection')->willReturn($connection);

        return new TenantFeatureOverrideManager($repo, $cache, $registry);
    }

    // ── setOverride ───────────────────────────────────────────────────────────

    public function testSetOverrideInsertsNewRecord(): void
    {
        $repo = $this->createStub(TenantFeatureOverrideRepositoryInterface::class);
        $repo->method('findByTenantAndKey')->willReturn(null); // no existing

        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())->method('insert')
            ->with('tenant_feature_overrides', self::arrayHasKey('tenant_id'));
        $conn->expects(self::never())->method('update');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects(self::once())->method('invalidateTags')
            ->with(['tenant_features_7']);

        $manager = $this->makeManager($repo, $conn, $cache);
        $manager->setOverride(7, 'excel_export', true, null, null, null);
    }

    public function testSetOverrideUpdatesExistingRecord(): void
    {
        $existing = new TenantFeatureOverride(
            id: 42, tenantId: 7, featureKey: 'excel_export',
            enabled: false, configJson: null,
            expiresAt: null, reason: null, cleanedAt: null,
            createdAt: new \DateTimeImmutable(),
        );

        $repo = $this->createStub(TenantFeatureOverrideRepositoryInterface::class);
        $repo->method('findByTenantAndKey')->willReturn($existing);

        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())->method('update')
            ->with('tenant_feature_overrides', self::arrayHasKey('is_enabled'), ['id' => 42]);
        $conn->expects(self::never())->method('insert');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects(self::once())->method('invalidateTags')
            ->with(['tenant_features_7']);

        $manager = $this->makeManager($repo, $conn, $cache);
        $manager->setOverride(7, 'excel_export', true, null, null, 'Promotion');
    }

    public function testSetOverrideEncodesConfigAsJson(): void
    {
        $repo = $this->createStub(TenantFeatureOverrideRepositoryInterface::class);
        $repo->method('findByTenantAndKey')->willReturn(null);

        $insertedData = [];
        $conn = $this->createStub(Connection::class);
        $conn->method('insert')->willReturnCallback(
            static function (string $table, array $data) use (&$insertedData): int {
                $insertedData = $data;
                return 1;
            }
        );

        $cache = $this->createStub(TagAwareCacheInterface::class);

        $manager = $this->makeManager($repo, $conn, $cache);
        $manager->setOverride(7, 'feature', true, ['max_rows' => 500], null, null);

        self::assertSame('{"max_rows":500}', $insertedData['config_json']);
    }

    public function testSetOverrideWithNullConfigStoresNull(): void
    {
        $repo = $this->createStub(TenantFeatureOverrideRepositoryInterface::class);
        $repo->method('findByTenantAndKey')->willReturn(null);

        $insertedData = [];
        $conn = $this->createStub(Connection::class);
        $conn->method('insert')->willReturnCallback(
            static function (string $table, array $data) use (&$insertedData): int {
                $insertedData = $data;
                return 1;
            }
        );

        $cache = $this->createStub(TagAwareCacheInterface::class);

        $manager = $this->makeManager($repo, $conn, $cache);
        $manager->setOverride(7, 'feature', false, null, null, null);

        self::assertNull($insertedData['config_json']);
    }

    // ── removeOverride ────────────────────────────────────────────────────────

    public function testRemoveOverrideDeletesAndInvalidatesCache(): void
    {
        $repo = $this->createStub(TenantFeatureOverrideRepositoryInterface::class);

        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())->method('delete')
            ->with('tenant_feature_overrides', ['tenant_id' => 7, 'feature_key' => 'excel_export']);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects(self::once())->method('invalidateTags')
            ->with(['tenant_features_7']);

        $manager = $this->makeManager($repo, $conn, $cache);
        $manager->removeOverride(7, 'excel_export');
    }
}
