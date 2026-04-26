<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Controller;

use App\ControlPlane\Billing\DTO\EntitlementsResponse;
use App\ControlPlane\Billing\Repository\TenantFeatureOverrideRepositoryInterface;
use App\ControlPlane\Billing\Service\TenantUsageSummaryInterface;
use App\ControlPlane\Tenant\Service\TenantManager;
use App\Platform\Feature\Contract\FeatureCheckerInterface;
use App\Platform\Tenant\Context\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/tenant/entitlements
 *
 * Returns the active tenant's plan, resource limits with current usage,
 * and feature flags.
 *
 * Optional debug mode (requires X-Debug-Entitlements: 1 header AND ROLE_ADMIN):
 * expands each feature to include its resolution source ("plan" or "override").
 *
 * AC-12: GET /api/tenant/entitlements retorna 200 con plan, limits y features del tenant activo.
 */
#[Route('/api/tenant/entitlements', name: 'api_tenant_entitlements', methods: ['GET'])]
final class EntitlementsController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantManager $tenantManager,
        private readonly TenantUsageSummaryInterface $quotaService,
        private readonly FeatureCheckerInterface $featureChecker,
        private readonly TenantFeatureOverrideRepositoryInterface $overrideRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null) {
            return $this->json(['error' => 'No tenant context'], Response::HTTP_UNAUTHORIZED);
        }

        $tenant = $this->tenantManager->getTenantByName($tenantName);
        if ($tenant === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        $plan = (string) ($tenant['plan'] ?? 'free');

        // --- Limits with current usage ---
        $usageSummary = $this->quotaService->getUsageSummary($tenantName);
        $limits = $this->buildLimitsPayload($usageSummary);

        // --- Features ---
        $rawEntitlements = $this->featureChecker->getEntitlements();
        $debugMode = $this->isDebugMode($request, $tenant);
        $features = $this->buildFeaturesPayload($rawEntitlements, $tenant, $debugMode);

        $response = new EntitlementsResponse(
            plan: $plan,
            limits: $limits,
            features: $features,
            debugMode: $debugMode,
        );

        return $this->json($response->toArray());
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Converts the usage summary from TenantQuotaService into the API payload format.
     * Excludes internal keys like "storage_bytes" and maps "storage_mb" correctly.
     *
     * @param array<string, array{current: int, limit: int}> $usageSummary
     * @return array<string, array{limit: int, current: int}>
     */
    private function buildLimitsPayload(array $usageSummary): array
    {
        $limits = [];

        // Standard quota resources (users, customers, products, invoices, branches)
        $quotaKeys = ['users', 'customers', 'products', 'invoices', 'branches'];
        foreach ($quotaKeys as $key) {
            if (isset($usageSummary[$key])) {
                $limits[$key] = [
                    'limit'   => $usageSummary[$key]['limit'],
                    'current' => $usageSummary[$key]['current'],
                ];
            }
        }

        // Storage: convert bytes → MB for the API response
        if (isset($usageSummary['storage_bytes'])) {
            $storageLimitBytes = $usageSummary['storage_bytes']['limit'];
            $storageCurrentBytes = $usageSummary['storage_bytes']['current'];

            $limits['storage_mb'] = [
                'limit'   => $storageLimitBytes === -1 ? -1 : (int) round($storageLimitBytes / (1024 * 1024)),
                'current' => (int) round($storageCurrentBytes / (1024 * 1024)),
            ];
        }

        return $limits;
    }

    /**
     * Builds the features payload, optionally enriched with source information.
     *
     * @param array<string, array{enabled: bool, config: array<string, mixed>}> $rawEntitlements
     * @param array<string, mixed>                                               $tenant
     * @return array<string, array{enabled: bool, source: string, config: array<string, mixed>}>
     */
    private function buildFeaturesPayload(array $rawEntitlements, array $tenant, bool $debugMode): array
    {
        // Build a set of feature keys that come from active overrides (for source resolution).
        $overrideKeys = [];
        if ($debugMode) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId > 0) {
                foreach ($this->overrideRepository->findActiveByTenant($tenantId) as $override) {
                    $overrideKeys[$override->featureKey] = true;
                }
            }
        }

        $features = [];
        foreach ($rawEntitlements as $key => $entitlement) {
            $source = isset($overrideKeys[$key]) ? 'override' : 'plan';
            $features[$key] = [
                'enabled' => $entitlement['enabled'],
                'source'  => $source,
                'config'  => $entitlement['config'],
            ];
        }

        return $features;
    }

    /**
     * Debug mode is active only when:
     *   - The request carries the header X-Debug-Entitlements: 1
     *   - AND the current user has ROLE_ADMIN
     */
    private function isDebugMode(Request $request, mixed $tenant): bool
    {
        if ($request->headers->get('X-Debug-Entitlements') !== '1') {
            return false;
        }

        return $this->isGranted('ROLE_ADMIN');
    }
}
