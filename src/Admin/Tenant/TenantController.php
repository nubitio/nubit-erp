<?php

declare(strict_types=1);

namespace App\Admin\Tenant;

use App\Core\Service\TenantManager;
use App\Core\Service\PlanManager;
use App\Core\Service\TenantQuotaService;
use App\Core\Service\TenantUsageService;
use App\Core\Service\GitHubWorkflowDispatcher;
use App\Core\Audit\AuditLogger;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TenantController extends AbstractController
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly PlanManager $planManager,
        private readonly TenantQuotaService $tenantQuotaService,
        private readonly TenantUsageService $tenantUsageService,
        private readonly AuditLogger $auditLogger,
        private readonly GitHubWorkflowDispatcher $workflowDispatcher,
    ) {
    }

    #[Route('/admin/tenants', name: 'admin_tenant_index')]
    public function index(): Response
    {
        $tenants = $this->tenantManager->getTenants();

        return $this->render('admin/tenant/index.html.twig', [
            'tenants' => $tenants,
        ]);
    }

    #[Route('/admin/tenants/{id}/edit', name: 'admin_tenant_edit', methods: ['GET'])]
    public function edit(int $id): Response
    {
        $tenant = $this->tenantManager->getTenant($id);

        if (null === $tenant) {
            $this->addFlash('error', 'Tenant not found');

            return $this->redirectToRoute('admin_tenant_index');
        }

        $domains = $this->tenantManager->getTenantDomains($id);
        $primaryDomain = $this->tenantManager->getPrimaryDomain($id);
        $plans = $this->planManager->getPlans();

        $quotaSummary = [];
        $usageLatest = [];
        $usageHistory = [];
        $quotaAlerts = [];

        try {
            $tenantName = (string)($tenant['name'] ?? '');
            if ($tenantName !== '') {
                $quotaSummary = $this->tenantQuotaService->getUsageSummary($tenantName);
                $usageLatest = $this->tenantUsageService->getLatestMetrics($tenantName);
                $usageHistory = $this->tenantUsageService->getRecentHistory($tenantName, 24);
                $quotaAlerts = $this->buildQuotaAlerts($quotaSummary);
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Unable to load usage summary: ' . $e->getMessage());
        }

        return $this->render('admin/tenant/edit.html.twig', [
            'tenant' => $tenant,
            'domains' => $domains,
            'primary_domain' => $primaryDomain,
            'plans' => $plans,
            'quota_summary' => $quotaSummary,
            'usage_latest' => $usageLatest,
            'usage_history' => $usageHistory,
            'quota_alerts' => $quotaAlerts,
        ]);
    }

    #[Route('/admin/tenants/create', name: 'admin_tenant_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_index');
        }

        $tenant = [
            'name' => trim((string)$request->request->get('name', '')),
            'connection' => trim((string)$request->request->get('connection', '')),
        ];

        try {
            $this->tenantManager->addTenant($tenant);
            $this->auditLogger->log('tenant.create', ['tenant' => $tenant]);
            $this->addFlash('success', 'Tenant created');
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (RuntimeException $exception) {
            $this->addFlash('error', 'Unable to save tenants: ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_index');
    }

    #[Route('/admin/tenants/{id}', name: 'admin_tenant_update', methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
        }

        $tenant = [
            'name' => trim((string)$request->request->get('name', '')),
            'connection' => trim((string)$request->request->get('connection', '')),
        ];

        try {
            $this->tenantManager->updateTenant($id, $tenant);
            $this->auditLogger->log('tenant.update', ['tenant_id' => $id, 'tenant' => $tenant]);
            $this->addFlash('success', 'Tenant updated');
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (RuntimeException $exception) {
            $this->addFlash('error', 'Unable to save tenants: ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_index');
    }

    #[Route('/admin/tenants/delete/{id}', name: 'admin_tenant_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_index');
        }

        $this->tenantManager->deleteTenant($id);
        $this->auditLogger->log('tenant.soft_delete', ['tenant_id' => $id]);
        $this->addFlash('success', 'Tenant deactivated (soft-deleted). Use purge for permanent removal.');

        return $this->redirectToRoute('admin_tenant_index');
    }

    #[Route('/admin/tenants/{id}/restore', name: 'admin_tenant_restore', methods: ['POST'])]
    public function restore(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_index');
        }

        try {
            $this->tenantManager->restoreTenant($id);
            $this->auditLogger->log('tenant.restore', ['tenant_id' => $id]);
            $this->addFlash('success', 'Tenant restored');
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_index');
    }

    #[Route('/admin/tenants/{id}/suspend', name: 'admin_tenant_suspend', methods: ['POST'])]
    public function suspend(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
        }

        try {
            $this->tenantManager->suspendTenant($id);
            $this->auditLogger->log('tenant.suspend', ['tenant_id' => $id]);
            $this->addFlash('success', 'Tenant suspended');
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
    }

    #[Route('/admin/tenants/{id}/unsuspend', name: 'admin_tenant_unsuspend', methods: ['POST'])]
    public function unsuspend(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
        }

        try {
            $this->tenantManager->unsuspendTenant($id);
            $this->auditLogger->log('tenant.unsuspend', ['tenant_id' => $id]);
            $this->addFlash('success', 'Tenant reactivated');
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
    }

    #[Route('/admin/tenants/{id}/purge', name: 'admin_tenant_purge', methods: ['POST'])]
    public function purge(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_index');
        }

        try {
            $this->tenantManager->purgeTenant($id);
            $this->auditLogger->log('tenant.purge', ['tenant_id' => $id]);
            $this->addFlash('success', 'Tenant permanently deleted');
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_index');
    }

    #[Route('/admin/tenants/{id}/domains', name: 'admin_tenant_domain_create', methods: ['POST'])]
    public function addDomain(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
        }

        $domain = trim((string)$request->request->get('domain', ''));
        $isPrimary = (bool)$request->request->get('is_primary', false);

        try {
            $this->tenantManager->addTenantDomain($id, $domain, $isPrimary);
            $this->auditLogger->log('tenant.domain.create', [
                'tenant_id' => $id,
                'domain' => $domain,
                'is_primary' => $isPrimary,
            ]);
            $this->addFlash('success', 'Domain added');
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (RuntimeException $exception) {
            $this->addFlash('error', 'Unable to add domain: ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
    }

    #[Route(
        '/admin/tenants/{tenantId}/domains/{domainId}/primary',
        name: 'admin_tenant_domain_primary',
        methods: ['POST']
    )]
    public function setPrimaryDomain(int $tenantId, int $domainId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $tenantId]);
        }

        try {
            $this->tenantManager->setPrimaryTenantDomain($tenantId, $domainId);
            $this->auditLogger->log('tenant.domain.primary', [
                'tenant_id' => $tenantId,
                'domain_id' => $domainId,
            ]);
            $this->addFlash('success', 'Primary domain updated');
        } catch (RuntimeException $exception) {
            $this->addFlash('error', 'Unable to update primary domain: ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $tenantId]);
    }

    #[Route(
        '/admin/tenants/{tenantId}/domains/{domainId}/delete',
        name: 'admin_tenant_domain_delete',
        methods: ['POST']
    )]
    public function deleteDomain(int $tenantId, int $domainId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $tenantId]);
        }

        $this->tenantManager->deleteTenantDomain($domainId);
        $this->auditLogger->log('tenant.domain.delete', [
            'tenant_id' => $tenantId,
            'domain_id' => $domainId,
        ]);
        $this->addFlash('success', 'Domain removed');

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $tenantId]);
    }

    #[Route('/admin/tenants/{id}/plan', name: 'admin_tenant_plan', methods: ['POST'])]
    public function changePlan(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
        }

        $plan = trim((string)$request->request->get('plan', ''));
        $expectsJson = $request->isXmlHttpRequest() || str_contains(
            strtolower((string)$request->headers->get('Accept', '')),
            'application/json'
        );

        if ($plan === '') {
            if ($expectsJson) {
                return $this->json(['error' => 'Plan is required'], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('error', 'Plan is required');

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
        }

        try {
            $this->tenantManager->changePlan($id, $plan);
            $this->auditLogger->log('tenant.plan.change', ['tenant_id' => $id, 'plan' => $plan]);

            if ($expectsJson) {
                return $this->json(['status' => 'ok', 'plan' => $plan]);
            }
            $this->addFlash('success', 'Plan updated');

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
        } catch (InvalidArgumentException $exception) {
            if ($expectsJson) {
                return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
        }
    }

    #[Route('/admin/tenants/{id}/usage', name: 'admin_tenant_usage', methods: ['GET'])]
    public function usage(int $id): JsonResponse
    {
        $tenant = $this->tenantManager->getTenant($id);
        if ($tenant === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        $tenantName = (string)($tenant['name'] ?? '');
        $quotaSummary = $this->tenantQuotaService->getUsageSummary($tenantName);
        $usageLatest = $this->tenantUsageService->getLatestMetrics($tenantName);
        $usageHistory = $this->tenantUsageService->getRecentHistory($tenantName, 24);

        return $this->json([
            'tenant' => $tenantName,
            'quota_summary' => $quotaSummary,
            'latest' => $usageLatest,
            'history' => $usageHistory,
            'alerts' => $this->buildQuotaAlerts($quotaSummary),
        ]);
    }

    #[Route('/admin/tenants/{id}/provision', name: 'admin_tenant_provision', methods: ['POST'])]
    public function provision(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
        }

        $tenant = $this->tenantManager->getTenant($id);
        if ($tenant === null) {
            $this->addFlash('error', 'Tenant not found');

            return $this->redirectToRoute('admin_tenant_index');
        }

        $adminUser = trim((string)$request->request->get('admin_user', 'admin'));
        $domain = trim((string)$request->request->get('domain', ''));

        if ($domain === '') {
            $domain = (string)($this->tenantManager->getPrimaryDomain($id) ?? '');
        }

        try {
            $this->workflowDispatcher->dispatch([
                'tenant_name' => (string)$tenant['name'],
                'domain' => $domain,
                'admin_user' => $adminUser,
            ]);
            $this->auditLogger->log('tenant.provision.dispatch', [
                'tenant_id' => $id,
                'domain' => $domain,
            ]);
            $this->addFlash('success', 'Provisioning workflow dispatched.');
        } catch (RuntimeException $exception) {
            $this->addFlash('error', 'Unable to dispatch provisioning: ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
    }

    /**
     * @param array<string, array{current:int, limit:int}> $quotaSummary
     *
     * @return array<int, array{resource: string, percent: float}>
     */
    private function buildQuotaAlerts(array $quotaSummary): array
    {
        $alerts = [];

        foreach ($quotaSummary as $resource => $usage) {
            $limit = (int)$usage['limit'];
            $current = (int)$usage['current'];
            if ($limit <= 0) {
                continue;
            }

            $percent = ($current / $limit) * 100;
            if ($percent >= 80.0) {
                $alerts[] = [
                    'resource' => $resource,
                    'percent' => round($percent, 2),
                ];
            }
        }

        usort($alerts, static fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return $alerts;
    }
}
