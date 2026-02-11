<?php

declare(strict_types=1);

namespace App\Tests\Core\Service;

use App\Core\Context\TenantContext;
use App\Core\Exception\ServiceException;
use App\Core\Service\PlanManager;
use App\Core\Service\TenantManager;
use App\Core\Service\TenantQuotaService;
use App\Core\Service\TenantResourceCatalog;
use Doctrine\Persistence\ConnectionRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;

final class TenantQuotaServiceTest extends TestCase
{
    private TenantManager&MockObject $tenantManager;
    private PlanManager $planManager;
    private ConnectionRegistry&MockObject $registry;
    private CacheInterface&MockObject $cache;
    private TenantResourceCatalog $resourceCatalog;
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        $this->tenantManager = $this->createMock(TenantManager::class);
        $this->registry = $this->createMock(ConnectionRegistry::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->resourceCatalog = new TenantResourceCatalog();
        $this->tenantContext = new TenantContext();
        $this->planManager = new PlanManager(
            $this->registry,
            $this->cache,
            new NullLogger(),
            $this->resourceCatalog,
        );
    }

    public function testCheckFailsClosedWhenTenantContextExistsButTenantIsMissing(): void
    {
        $this->tenantContext->setTenant(10, 'acme', 'acme.example.com', 'req-1');
        $this->tenantManager
            ->expects($this->once())
            ->method('getTenantByName')
            ->with('acme')
            ->willReturn(null);

        $service = new TenantQuotaService(
            $this->tenantContext,
            $this->tenantManager,
            $this->planManager,
            $this->resourceCatalog,
            $this->registry
        );

        $result = $service->check('users');

        $this->assertFalse($result->allowed);
        $this->assertSame(0, $result->current);
        $this->assertSame(0, $result->limit);
    }

    public function testEnforceThrows429WhenFailClosed(): void
    {
        $this->tenantContext->setTenant(10, 'acme', 'acme.example.com', 'req-1');
        $this->tenantManager
            ->method('getTenantByName')
            ->with('acme')
            ->willReturn(null);

        $service = new TenantQuotaService(
            $this->tenantContext,
            $this->tenantManager,
            $this->planManager,
            $this->resourceCatalog,
            $this->registry
        );

        $this->expectException(ServiceException::class);
        $this->expectExceptionCode(429);
        $service->enforce('users');
    }
}
