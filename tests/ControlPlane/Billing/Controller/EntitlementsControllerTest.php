<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Billing\Controller;

use App\ControlPlane\Billing\Controller\EntitlementsController;
use App\ControlPlane\Billing\DTO\EntitlementsResponse;
use App\ControlPlane\Billing\Entity\TenantFeatureOverride;
use App\ControlPlane\Billing\Repository\TenantFeatureOverrideRepositoryInterface;
use App\ControlPlane\Billing\Service\TenantUsageSummaryInterface;
use App\ControlPlane\Tenant\Application\TenantService;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Unit tests for EntitlementsController.
 *
 * Covers SPEC-005 AC-12:
 *   GET /api/tenant/entitlements returns 200 with plan, limits and features.
 *
 * Also covers the conditional `source` field logic (X-Debug-Entitlements + ROLE_ADMIN).
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class EntitlementsControllerTest extends TestCase
{
    private TenantContext&MockObject $tenantContext;
    private TenantService&MockObject $tenantService;
    private TenantUsageSummaryInterface&MockObject $quotaService;
    private FeatureCheckerInterface&MockObject $featureChecker;
    private TenantFeatureOverrideRepositoryInterface&MockObject $overrideRepository;

    protected function setUp(): void
    {
        $this->tenantContext = $this->createMock(TenantContext::class);
        $this->tenantService = $this->createMock(TenantService::class);
        $this->quotaService = $this->createMock(TenantUsageSummaryInterface::class);
        $this->featureChecker = $this->createMock(FeatureCheckerInterface::class);
        $this->overrideRepository = $this->createMock(TenantFeatureOverrideRepositoryInterface::class);
    }

    // -------------------------------------------------------------------------
    // EntitlementsResponse DTO tests
    // -------------------------------------------------------------------------

    public function testDtoSerializesSimpleFeaturesWhenNotInDebugMode(): void
    {
        $dto = new EntitlementsResponse(
            plan: 'basic',
            limits: [
                'users' => ['limit' => 10, 'current' => 3],
            ],
            features: [
                'excel_export' => ['enabled' => true, 'source' => 'plan', 'config' => []],
                'api_access' => ['enabled' => false, 'source' => 'plan', 'config' => []],
            ],
            debugMode: false,
        );

        $payload = $dto->toArray();

        self::assertSame('basic', $payload['plan']);
        self::assertSame(['limit' => 10, 'current' => 3], $payload['limits']['users']);
        // In normal mode features are flat booleans
        self::assertTrue($payload['features']['excel_export']);
        self::assertFalse($payload['features']['api_access']);
    }

    public function testDtoSerializesVerboseFeaturesInDebugMode(): void
    {
        $dto = new EntitlementsResponse(
            plan: 'basic',
            limits: [],
            features: [
                'excel_export' => ['enabled' => true, 'source' => 'plan', 'config' => []],
                'api_access' => ['enabled' => false, 'source' => 'override', 'config' => ['reason' => 'trial']],
            ],
            debugMode: true,
        );

        $payload = $dto->toArray();

        self::assertIsArray($payload['features']['excel_export']);
        self::assertTrue($payload['features']['excel_export']['enabled']);
        self::assertSame('plan', $payload['features']['excel_export']['source']);
        self::assertSame([], $payload['features']['excel_export']['config']);

        self::assertIsArray($payload['features']['api_access']);
        self::assertFalse($payload['features']['api_access']['enabled']);
        self::assertSame('override', $payload['features']['api_access']['source']);
        self::assertSame(['reason' => 'trial'], $payload['features']['api_access']['config']);
    }

    // -------------------------------------------------------------------------
    // Controller integration-style unit tests
    // -------------------------------------------------------------------------

    public function testReturns401WhenNoTenantContext(): void
    {
        $this->tenantContext->method('getTenantName')->willReturn(null);

        $controller = $this->buildController();
        $response = $controller->__invoke(new Request());

        self::assertSame(401, $response->getStatusCode());
    }

    public function testReturns404WhenTenantNotFound(): void
    {
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->tenantService->method('getTenantByName')->with('acme')->willReturn(null);

        $controller = $this->buildController();
        $response = $controller->__invoke(new Request());

        self::assertSame(404, $response->getStatusCode());
    }

    public function testReturns200WithPlanLimitsAndFeatures(): void
    {
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->tenantService
            ->method('getTenantByName')
            ->willReturn([
                'id' => 1,
                'name' => 'acme',
                'plan' => 'basic',
            ]);
        $this->quotaService
            ->method('getUsageSummary')
            ->willReturn([
                'users' => ['current' => 3, 'limit' => 10],
                'customers' => ['current' => 247, 'limit' => 1000],
                'products' => ['current' => 89, 'limit' => 500],
                'invoices' => ['current' => 1247, 'limit' => 5000],
                'branches' => ['current' => 1, 'limit' => 5],
                'storage_bytes' => ['current' => 47185920, 'limit' => 1048576000], // 45 MB / 1000 MB
            ]);
        $this->featureChecker
            ->method('getEntitlements')
            ->willReturn([
                'excel_export' => ['enabled' => true, 'config' => []],
                'pdf_bulk_download' => ['enabled' => true, 'config' => []],
                'api_access' => ['enabled' => false, 'config' => []],
                'advanced_reports' => ['enabled' => true, 'config' => []],
            ]);

        $controller = $this->buildController();
        $response = $controller->__invoke(new Request());

        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('basic', $data['plan']);

        // Limits
        self::assertSame(['limit' => 10, 'current' => 3], $data['limits']['users']);
        self::assertSame(['limit' => 1000, 'current' => 247], $data['limits']['customers']);
        self::assertSame(['limit' => 500, 'current' => 89], $data['limits']['products']);
        self::assertSame(['limit' => 5000, 'current' => 1247], $data['limits']['invoices']);
        self::assertSame(['limit' => 5, 'current' => 1], $data['limits']['branches']);
        self::assertSame(['limit' => 1000, 'current' => 45], $data['limits']['storage_mb']);

        // Features (flat booleans in normal mode)
        self::assertTrue($data['features']['excel_export']);
        self::assertTrue($data['features']['pdf_bulk_download']);
        self::assertFalse($data['features']['api_access']);
        self::assertTrue($data['features']['advanced_reports']);
    }

    public function testDebugModeRequiresBothHeaderAndAdminRole(): void
    {
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->tenantService
            ->method('getTenantByName')
            ->willReturn([
                'id' => 1,
                'name' => 'acme',
                'plan' => 'basic',
            ]);
        $this->quotaService->method('getUsageSummary')->willReturn([]);
        $this->featureChecker
            ->method('getEntitlements')
            ->willReturn([
                'excel_export' => ['enabled' => true, 'config' => []],
            ]);
        // No active overrides
        $this->overrideRepository->method('findActiveByTenant')->willReturn([]);

        // Request WITH header but user is NOT admin → normal mode
        $request = new Request();
        $request->headers->set('X-Debug-Entitlements', '1');

        $controller = $this->buildController(isAdmin: false);
        $response = $controller->__invoke($request);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);
        // Should be flat boolean, not verbose array
        self::assertTrue($data['features']['excel_export']);
    }

    public function testDebugModeShowsSourceWhenHeaderAndAdminPresent(): void
    {
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->tenantService
            ->method('getTenantByName')
            ->willReturn([
                'id' => 42,
                'name' => 'acme',
                'plan' => 'basic',
            ]);
        $this->quotaService->method('getUsageSummary')->willReturn([]);
        $this->featureChecker
            ->method('getEntitlements')
            ->willReturn([
                'excel_export' => ['enabled' => true, 'config' => []],
                'api_access' => ['enabled' => false, 'config' => []],
            ]);

        // api_access comes from an active override
        $override = new TenantFeatureOverride(
            id: 1,
            tenantId: 42,
            featureKey: 'api_access',
            enabled: false,
            configJson: [],
            expiresAt: null,
            reason: 'test',
            cleanedAt: null,
            createdAt: new \DateTimeImmutable(),
        );
        $this->overrideRepository->method('findActiveByTenant')->with(42)->willReturn([$override]);

        $request = new Request();
        $request->headers->set('X-Debug-Entitlements', '1');

        $controller = $this->buildController(isAdmin: true);
        $response = $controller->__invoke($request);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true);

        // excel_export → from plan
        self::assertIsArray($data['features']['excel_export']);
        self::assertSame('plan', $data['features']['excel_export']['source']);
        self::assertTrue($data['features']['excel_export']['enabled']);

        // api_access → from override
        self::assertIsArray($data['features']['api_access']);
        self::assertSame('override', $data['features']['api_access']['source']);
        self::assertFalse($data['features']['api_access']['enabled']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildController(bool $isAdmin = false): EntitlementsController
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->with('ROLE_ADMIN')->willReturn($isAdmin);

        $controller = new EntitlementsController(
            $this->tenantContext,
            $this->tenantService,
            $this->quotaService,
            $this->featureChecker,
            $this->overrideRepository,
        );

        // Inject the authorization checker via the container (AbstractController uses it internally).
        $container = new \Symfony\Component\DependencyInjection\Container();
        $container->set('security.authorization_checker', $authChecker);
        $controller->setContainer($container);

        return $controller;
    }
}
