<?php

declare(strict_types=1);

namespace App\Admin\Tenant;

use App\Core\Service\TenantManager;
use App\Core\Service\PlanManager;
use App\Core\Service\TenantQuotaService;
use App\Core\Service\TenantUsageService;
use App\Core\Service\TenantBackupService;
use App\Core\Service\GitHubWorkflowDispatcher;
use App\Core\Audit\AuditLogger;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
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
        private readonly TenantBackupService $backupService,
        private readonly AuditLogger $auditLogger,
        private readonly GitHubWorkflowDispatcher $workflowDispatcher,
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/admin/tenants', name: 'admin_tenant_index')]
    public function index(): Response
    {
        $tenants = $this->tenantManager->getTenants();
        $deletedTenants = $this->tenantManager->getDeletedTenants();

        return $this->render('admin/tenant/index.html.twig', [
            'tenants' => $tenants,
            'deleted_tenants' => $deletedTenants,
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
        $usageDeltas = [];
        $quotaAlerts = [];
        $backups = [];
        $provisions = [];

        try {
            $tenantName = (string)($tenant['name'] ?? '');
            if ($tenantName !== '') {
                $quotaSummary = $this->tenantQuotaService->getUsageSummary($tenantName);
                $usageLatest = $this->tenantUsageService->getLatestMetrics($tenantName);
                $usageDeltas = $this->tenantUsageService->getUsageDeltas($tenantName, 24);
                $quotaAlerts = $this->buildQuotaAlerts($quotaSummary);
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Unable to load usage summary: ' . $e->getMessage());
        }

        try {
            $backups = $this->backupService->listBackupsByTenantId($id);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Unable to load backups: ' . $e->getMessage());
        }

        try {
            $provisions = $this->loadProvisions($id);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Unable to load provision history: ' . $e->getMessage());
        }

        return $this->render('admin/tenant/edit.html.twig', [
            'tenant' => $tenant,
            'domains' => $domains,
            'primary_domain' => $primaryDomain,
            'plans' => $plans,
            'quota_summary' => $quotaSummary,
            'usage_latest' => $usageLatest,
            'usage_deltas' => $usageDeltas,
            'quota_alerts' => $quotaAlerts,
            'backups' => $backups,
            'provisions' => $provisions,
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

        $provisionId = 0;

        try {
            $targetContainer = $this->workflowDispatcher->getTargetContainer();
            $provisionId = $this->recordProvisionDispatch(
                $id,
                (string)$tenant['name'],
                $domain,
                $adminUser,
                $targetContainer
            );

            $this->workflowDispatcher->dispatch([
                'tenant_name' => (string)$tenant['name'],
                'domain' => $domain,
                'admin_user' => $adminUser,
                'target_container' => $targetContainer,
                'provision_id' => (string)$provisionId,
            ]);
            $this->auditLogger->log('tenant.provision.dispatch', [
                'tenant_id' => $id,
                'provision_id' => $provisionId,
                'domain' => $domain,
            ]);
            $this->addFlash('success', 'Provisioning workflow dispatched.');
        } catch (RuntimeException $exception) {
            $this->logger->error('Provisioning dispatch failed for tenant {tenant}', [
                'tenant_id' => $id,
                'provision_id' => $provisionId,
                'exception' => $exception,
            ]);
            if ($provisionId > 0) {
                $this->markProvisionFailed($provisionId, 'Workflow dispatch failed');
            }
            $this->addFlash('error', 'Unable to dispatch provisioning. Check logs for details.');
        }

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
    }

    #[Route('/admin/tenants/{id}/backup-frequency', name: 'admin_tenant_backup_frequency', methods: ['POST'])]
    public function updateBackupFrequency(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
        }

        $frequency = (int) $request->request->get('backup_frequency', 0);

        try {
            $this->tenantManager->updateBackupFrequency($id, $frequency);
            $this->auditLogger->log('tenant.backup_frequency.update', [
                'tenant_id' => $id,
                'backup_frequency' => $frequency,
            ]);
            $this->addFlash('success', 'Backup frequency updated.');
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $id, '_fragment' => 'backups']);
    }

    #[Route('/admin/tenants/{id}/backups', name: 'admin_tenant_backups', methods: ['GET'])]
    public function backups(int $id): Response
    {
        $tenant = $this->tenantManager->getTenant($id);
        if ($tenant === null) {
            $this->addFlash('error', 'Tenant not found');

            return $this->redirectToRoute('admin_tenant_index');
        }

        $backups = $this->backupService->listBackupsByTenantId($id);

        return $this->json($backups);
    }

    #[Route('/admin/tenants/{id}/backup', name: 'admin_tenant_backup_create', methods: ['POST'])]
    public function createBackup(int $id, Request $request): Response
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

        try {
            $result = $this->backupService->backup((string) $tenant['name'], true);
            $this->auditLogger->log('tenant.backup.create', [
                'tenant_id' => $id,
                'backup_id' => $result['id'],
                'filename' => $result['filename'],
            ]);
            $this->addFlash('success', sprintf('Backup created: %s', $result['filename']));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Backup failed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $id, '_fragment' => 'backups']);
    }

    #[Route('/admin/tenants/{id}/backup/{backupId}/restore', name: 'admin_tenant_backup_restore', methods: ['POST'])]
    public function restoreBackup(int $id, int $backupId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
        }

        try {
            $this->backupService->restore($backupId);
            $this->auditLogger->log('tenant.backup.restore', [
                'tenant_id' => $id,
                'backup_id' => $backupId,
            ]);
            $this->addFlash('success', sprintf('Tenant restored from backup #%d.', $backupId));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Restore failed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $id, '_fragment' => 'backups']);
    }

    #[Route('/admin/tenants/{id}/backup/{backupId}/delete', name: 'admin_tenant_backup_delete', methods: ['POST'])]
    public function deleteBackup(int $id, int $backupId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
        }

        try {
            $this->backupService->deleteBackup($backupId);
            $this->auditLogger->log('tenant.backup.delete', [
                'tenant_id' => $id,
                'backup_id' => $backupId,
            ]);
            $this->addFlash('success', sprintf('Backup #%d deleted.', $backupId));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Delete failed: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $id, '_fragment' => 'backups']);
    }

    private function recordProvisionDispatch(
        int $tenantId,
        string $tenantName,
        string $domain,
        string $adminUser,
        string $targetContainer,
    ): int {
        /** @var Connection $cp */
        $cp = $this->connectionRegistry->getConnection('control_plane');

        $sql = 'INSERT INTO tenant_provisions'
            . ' (tenant_id, tenant_name, domain, admin_user, target_container, status, created_at)'
            . ' VALUES (?, ?, ?, ?, ?, \'dispatched\', NOW())';

        $cp->executeStatement(
            $sql,
            [$tenantId, $tenantName, $domain, $adminUser, $targetContainer],
            [
                ParameterType::INTEGER,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
                ParameterType::STRING,
            ]
        );

        return (int)$cp->lastInsertId();
    }

    private function markProvisionFailed(int $provisionId, string $error): void
    {
        try {
            /** @var Connection $cp */
            $cp = $this->connectionRegistry->getConnection('control_plane');
            $cp->executeStatement(
                'UPDATE tenant_provisions SET status = ?, error_message = ?, completed_at = NOW() WHERE id = ?',
                ['failed', mb_substr($error, 0, 65535), $provisionId],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]
            );
        } catch (\Throwable) {
            // Don't let tracking failures mask the original error
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadProvisions(int $tenantId): array
    {
        /** @var Connection $cp */
        $cp = $this->connectionRegistry->getConnection('control_plane');

        if (!$cp->createSchemaManager()->tablesExist(['tenant_provisions'])) {
            return [];
        }

        return $cp->fetchAllAssociative(
            'SELECT * FROM tenant_provisions WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ?',
            [$tenantId, 20],
            [ParameterType::INTEGER, ParameterType::INTEGER]
        );
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
