<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Controller;

use App\ControlPlane\Billing\Feature\PlanFeatureManager;
use App\ControlPlane\Billing\Feature\TenantFeatureOverrideManager;
use App\ControlPlane\Billing\Repository\TenantFeatureOverrideRepositoryInterface;
use App\ControlPlane\Tenant\Service\TenantManager;
use App\Efact\Audit\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin CRUD for tenant_feature_overrides.
 *
 * Routes:
 *   GET  /admin/tenants/{id}/features              → list overrides
 *   GET  /admin/tenants/{id}/features/new          → create form
 *   POST /admin/tenants/{id}/features              → save new override
 *   GET  /admin/tenants/{id}/features/{key}/edit   → edit form
 *   POST /admin/tenants/{id}/features/{key}        → update override
 *   POST /admin/tenants/{id}/features/{key}/delete → delete override
 *
 * AC-6:  Active override has priority over plan_features (handled by TenantFeatureChecker).
 * AC-7:  Expired override is ignored (handled by TenantFeatureChecker).
 * AC-14: Cache is invalidated on every mutation via TenantFeatureOverrideManager.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/tenants/{id}/features', requirements: ['id' => '\d+'])]
final class TenantFeatureOverrideController extends AbstractController
{
    /** The canonical feature key catalogue — sourced from PlanFeatureManager. */
    private const array FEATURE_KEYS = PlanFeatureManager::FEATURE_KEYS;

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly TenantFeatureOverrideManager $overrideManager,
        private readonly TenantFeatureOverrideRepositoryInterface $overrideRepository,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    #[Route('', name: 'admin_tenant_features_index', methods: ['GET'])]
    public function index(int $id): Response
    {
        $tenant = $this->tenantManager->getTenant($id);
        if ($tenant === null) {
            $this->addFlash('error', 'Tenant not found.');

            return $this->redirectToRoute('admin_tenant_index');
        }

        $overrides = $this->overrideRepository->findAllByTenant($id);

        return $this->render('admin/tenant/features/index.html.twig', [
            'tenant'       => $tenant,
            'overrides'    => $overrides,
            'feature_keys' => self::FEATURE_KEYS,
        ]);
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    #[Route('/new', name: 'admin_tenant_features_new', methods: ['GET'])]
    public function new(int $id): Response
    {
        $tenant = $this->tenantManager->getTenant($id);
        if ($tenant === null) {
            $this->addFlash('error', 'Tenant not found.');

            return $this->redirectToRoute('admin_tenant_index');
        }

        return $this->render('admin/tenant/features/form.html.twig', [
            'tenant'       => $tenant,
            'override'     => null,
            'mode'         => 'create',
            'feature_keys' => self::FEATURE_KEYS,
            'values'       => [
                'featureKey' => '',
                'isEnabled'  => true,
                'configJson' => '',
                'expiresAt'  => '',
                'reason'     => '',
            ],
        ]);
    }

    #[Route('', name: 'admin_tenant_features_create', methods: ['POST'])]
    public function create(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_features_index', ['id' => $id]);
        }

        $tenant = $this->tenantManager->getTenant($id);
        if ($tenant === null) {
            $this->addFlash('error', 'Tenant not found.');

            return $this->redirectToRoute('admin_tenant_index');
        }

        $featureKey = trim((string) $request->request->get('featureKey', ''));
        $isEnabled  = (bool) $request->request->get('isEnabled', false);
        $configJson = trim((string) $request->request->get('configJson', ''));
        $expiresAt  = trim((string) $request->request->get('expiresAt', ''));
        $reason     = trim((string) $request->request->get('reason', ''));

        if (!in_array($featureKey, self::FEATURE_KEYS, true)) {
            $this->addFlash('error', sprintf('Invalid feature key: "%s".', $featureKey));

            return $this->redirectToRoute('admin_tenant_features_new', ['id' => $id]);
        }

        try {
            $configArray    = $this->parseConfigJson($configJson !== '' ? $configJson : null);
            $expiresAtDate  = $expiresAt !== '' ? new \DateTimeImmutable($expiresAt) : null;

            $this->overrideManager->setOverride(
                tenantId:   $id,
                featureKey: $featureKey,
                isEnabled:  $isEnabled,
                config:     $configArray,
                expiresAt:  $expiresAtDate,
                reason:     $reason !== '' ? $reason : null,
            );

            $this->auditLogger->log('tenant.feature_override.create', [
                'tenant_id'   => $id,
                'tenant_name' => (string) ($tenant['name'] ?? ''),
                'feature_key' => $featureKey,
                'is_enabled'  => $isEnabled,
                'expires_at'  => $expiresAtDate?->format('Y-m-d H:i:s'),
            ]);

            $this->addFlash('success', sprintf('Feature override for "%s" created successfully.', $featureKey));
        } catch (\JsonException $e) {
            $this->addFlash('error', 'Config JSON is invalid: ' . $e->getMessage());

            return $this->redirectToRoute('admin_tenant_features_new', ['id' => $id]);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Failed to create override: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_features_index', ['id' => $id]);
    }

    // -------------------------------------------------------------------------
    // Edit / Update
    // -------------------------------------------------------------------------

    #[Route('/{key}/edit', name: 'admin_tenant_features_edit', methods: ['GET'])]
    public function edit(int $id, string $key): Response
    {
        $tenant = $this->tenantManager->getTenant($id);
        if ($tenant === null) {
            $this->addFlash('error', 'Tenant not found.');

            return $this->redirectToRoute('admin_tenant_index');
        }

        $override = $this->overrideRepository->findByTenantAndKey($id, $key);
        if ($override === null) {
            $this->addFlash('error', sprintf('No override found for feature "%s".', $key));

            return $this->redirectToRoute('admin_tenant_features_index', ['id' => $id]);
        }

        return $this->render('admin/tenant/features/form.html.twig', [
            'tenant'       => $tenant,
            'override'     => $override,
            'mode'         => 'edit',
            'feature_keys' => self::FEATURE_KEYS,
            'values'       => [
                'featureKey' => $override->featureKey,
                'isEnabled'  => $override->isEnabled(),
                'configJson' => $override->getConfigJson() !== null
                    ? json_encode($override->getConfigJson(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
                    : '',
                'expiresAt'  => $override->expiresAt?->format('Y-m-d\TH:i') ?? '',
                'reason'     => $override->reason ?? '',
            ],
        ]);
    }

    #[Route('/{key}', name: 'admin_tenant_features_update', methods: ['POST'])]
    public function update(int $id, string $key, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_features_index', ['id' => $id]);
        }

        $tenant = $this->tenantManager->getTenant($id);
        if ($tenant === null) {
            $this->addFlash('error', 'Tenant not found.');

            return $this->redirectToRoute('admin_tenant_index');
        }

        $isEnabled  = (bool) $request->request->get('isEnabled', false);
        $configJson = trim((string) $request->request->get('configJson', ''));
        $expiresAt  = trim((string) $request->request->get('expiresAt', ''));
        $reason     = trim((string) $request->request->get('reason', ''));

        try {
            $configArray   = $this->parseConfigJson($configJson !== '' ? $configJson : null);
            $expiresAtDate = $expiresAt !== '' ? new \DateTimeImmutable($expiresAt) : null;

            $this->overrideManager->setOverride(
                tenantId:   $id,
                featureKey: $key,
                isEnabled:  $isEnabled,
                config:     $configArray,
                expiresAt:  $expiresAtDate,
                reason:     $reason !== '' ? $reason : null,
            );

            $this->auditLogger->log('tenant.feature_override.update', [
                'tenant_id'   => $id,
                'tenant_name' => (string) ($tenant['name'] ?? ''),
                'feature_key' => $key,
                'is_enabled'  => $isEnabled,
                'expires_at'  => $expiresAtDate?->format('Y-m-d H:i:s'),
            ]);

            $this->addFlash('success', sprintf('Feature override for "%s" updated successfully.', $key));
        } catch (\JsonException $e) {
            $this->addFlash('error', 'Config JSON is invalid: ' . $e->getMessage());

            return $this->redirectToRoute('admin_tenant_features_edit', ['id' => $id, 'key' => $key]);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Failed to update override: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_features_index', ['id' => $id]);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    #[Route('/{key}/delete', name: 'admin_tenant_features_delete', methods: ['POST'])]
    public function delete(int $id, string $key, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_features_index', ['id' => $id]);
        }

        $tenant = $this->tenantManager->getTenant($id);
        if ($tenant === null) {
            $this->addFlash('error', 'Tenant not found.');

            return $this->redirectToRoute('admin_tenant_index');
        }

        try {
            $this->overrideManager->removeOverride($id, $key);

            $this->auditLogger->log('tenant.feature_override.delete', [
                'tenant_id'   => $id,
                'tenant_name' => (string) ($tenant['name'] ?? ''),
                'feature_key' => $key,
            ]);

            $this->addFlash('success', sprintf('Feature override for "%s" deleted.', $key));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Failed to delete override: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_features_index', ['id' => $id]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Parses a raw JSON string into an associative array, or returns null for empty input.
     *
     * @return array<string, mixed>|null
     *
     * @throws \JsonException on invalid JSON
     */
    private function parseConfigJson(?string $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '' || $trimmed === 'null') {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
