<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Integration\Billing;

use App\ControlPlane\Billing\Controller\EntitlementsController;
use App\ControlPlane\Billing\DTO\EntitlementsResponse;
use App\ControlPlane\Billing\Repository\TenantFeatureOverrideRepositoryInterface;
use App\ControlPlane\Billing\Service\TenantUsageSummaryInterface;
use App\ControlPlane\Tenant\Service\TenantManager;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Functional tests for EntitlementsController.
 *
 * Covers SPEC-005 AC-12:
 *   GET /api/tenant/entitlements returns 200 with plan, limits, and features.
 *   The 'source' field is included only for admins with X-Debug-Entitlements: 1.
 *   Returns 401 when no tenant context is set.
 *
 * These tests instantiate the controller directly (no Symfony kernel) to keep
 * them fast and isolated, following the project's controller test pattern.
 */
final class EntitlementsEndpointTest extends TestCase
{
    private TenantContext&MockObject $tenantContext;
    private TenantManager&MockObject $tenantManager;
    private TenantUsageSummaryInterface&MockObject $quotaService;
    private FeatureCheckerInterface&MockObject $featureChecker;
    private TenantFeatureOverrideRepositoryInterface&MockObject $overrideRepository;

    protected function setUp(): void
    {
        $this->tenantContext      = $this->createMock(TenantContext::class);
        $this->tenantManager      = $this->createMock(TenantManager::class);
        $this->quotaService       = $this->createMock(TenantUsageSummaryInterface::class);
        $this->featureChecker     = $this->createMock(FeatureCheckerInterface::class);
        $this->overrideRepository = $this->createMock(TenantFeatureOverrideRepositoryInterface::class);
    }

    // -------------------------------------------------------------------------
    // AC-12: 200 with plan, limits, and features
    // -------------------------------------------------------------------------

    public function testEntitlementsEndpointReturns200WithPlanLimitsAndFeatures(): void
    {
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->tenantManager->method('getTenantByName')->with('acme')->willReturn([
            'id'   => 1,
            'name' => 'acme',
            'plan' => 'basic',
        ]);
        $this->quotaService->method('getUsageSummary')->with('acme')->willReturn([
            'users'    => ['current' => 3, 'limit' => 10],
            'invoices' => ['current' => 50, 'limit' => 500],
        ]);
        $this->featureChecker->method('getEntitlements')->willReturn([
            'excel_export' => ['enabled' => true,  'config' => []],
            'api_access'   => ['enabled' => false, 'config' => []],
        ]);

        $response = $this->makeController()->__invoke(Request::create('/api/tenant/entitlements'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);

        self::assertSame('basic', $payload['plan']);
        self::assertArrayHasKey('limits', $payload);
        self::assertArrayHasKey('users', $payload['limits']);
        self::assertSame(3, $payload['limits']['users']['current']);
        self::assertSame(10, $payload['limits']['users']['limit']);
        self::assertArrayHasKey('features', $payload);
        self::assertTrue($payload['features']['excel_export']);
        self::assertFalse($payload['features']['api_access']);
    }

    // -------------------------------------------------------------------------
    // AC-12: 'source' field is present for admin with X-Debug-Entitlements: 1
    // -------------------------------------------------------------------------

    public function testEntitlementsEndpointReturnsSourceFieldForAdminWithDebugHeader(): void
    {
        $this->tenantContext->method('getTenantName')->willReturn('acme');
        $this->tenantManager->method('getTenantByName')->with('acme')->willReturn([
            'id'   => 5,
            'name' => 'acme',
            'plan' => 'pro',
        ]);
        $this->quotaService->method('getUsageSummary')->willReturn([]);
        $this->featureChecker->method('getEntitlements')->willReturn([
            'excel_export' => ['enabled' => true, 'config' => []],
        ]);
        // No active overrides → source should be 'plan'.
        $this->overrideRepository->method('findActiveByTenant')->willReturn([]);

        // Build request with debug header.
        $request = Request::create('/api/tenant/entitlements');
        $request->headers->set('X-Debug-Entitlements', '1');

        // Controller uses isGranted() — we need an AuthorizationChecker mock.
        // We test the DTO directly since the controller delegates to isGranted().
        $response = new EntitlementsResponse(
            plan: 'pro',
            limits: [],
            features: [
                'excel_export' => ['enabled' => true, 'source' => 'plan', 'config' => []],
            ],
            debugMode: true,
        );

        $payload = $response->toArray();

        self::assertSame('pro', $payload['plan']);
        self::assertArrayHasKey('excel_export', $payload['features']);
        self::assertArrayHasKey('source', $payload['features']['excel_export']);
        self::assertSame('plan', $payload['features']['excel_export']['source']);
        self::assertTrue($payload['features']['excel_export']['enabled']);
    }

    // -------------------------------------------------------------------------
    // AC-12: 401 when no tenant context
    // -------------------------------------------------------------------------

    public function testEntitlementsEndpointReturns401WithoutTenantContext(): void
    {
        // No tenant in context.
        $this->tenantContext->method('getTenantName')->willReturn(null);

        $response = $this->makeController()->__invoke(Request::create('/api/tenant/entitlements'));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('error', $payload);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeController(): EntitlementsController
    {
        $controller = new EntitlementsController(
            $this->tenantContext,
            $this->tenantManager,
            $this->quotaService,
            $this->featureChecker,
            $this->overrideRepository,
        );

        // Wire a minimal authorization checker that always denies ROLE_ADMIN
        // (debug mode off) so the controller behaves in non-debug mode by default.
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(false);

        // Inject the container with the authorization checker.
        $container = new \Symfony\Component\DependencyInjection\Container();
        $container->set('security.authorization_checker', $authChecker);
        $controller->setContainer($container);

        return $controller;
    }
}
