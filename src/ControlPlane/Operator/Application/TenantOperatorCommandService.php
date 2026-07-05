<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Application;

use App\ControlPlane\Message\TenantBackupMessage;
use App\ControlPlane\Tenant\Application\TenantService;
use App\ControlPlane\Tenant\Repository\TenantProvisionRepository;
use App\ControlPlane\Tenant\Service\ProvisionCommandRequest;
use App\ControlPlane\Tenant\Service\ProvisionCommandRunner;
use App\ControlPlane\Tenant\Service\TenantBackupService;
use App\Efact\Audit\AuditLogger;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Write operations for the operator tenant hub (API layer delegates here).
 */
final readonly class TenantOperatorCommandService
{
    public function __construct(
        private TenantService $tenantService,
        private TenantProvisionRepository $provisionRepository,
        private ProvisionCommandRunner $commandRunner,
        private TenantBackupService $backupService,
        private AuditLogger $auditLogger,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}

    public function changePlan(int $id, string $plan): void
    {
        $this->tenantService->changePlan($id, $plan);
        $this->auditLogger->log('tenant.plan.change', ['tenant_id' => $id, 'plan' => $plan]);
    }

    public function addDomain(int $id, string $domain, bool $isPrimary): void
    {
        $this->tenantService->addTenantDomain($id, $domain, $isPrimary);
        $this->auditLogger->log('tenant.domain.create', [
            'tenant_id' => $id,
            'domain' => $domain,
            'is_primary' => $isPrimary,
        ]);
    }

    public function setPrimaryDomain(int $tenantId, int $domainId): void
    {
        $this->tenantService->setPrimaryTenantDomain($tenantId, $domainId);
        $this->auditLogger->log('tenant.domain.primary', [
            'tenant_id' => $tenantId,
            'domain_id' => $domainId,
        ]);
    }

    public function deleteDomain(int $tenantId, int $domainId): void
    {
        $this->tenantService->deleteTenantDomain($domainId);
        $this->auditLogger->log('tenant.domain.delete', [
            'tenant_id' => $tenantId,
            'domain_id' => $domainId,
        ]);
    }

    /**
     * @return array{ok: bool, provisionId: int|null, output?: string}
     */
    public function provision(int $id, string $adminUser, string $adminPassword, string $domain): array
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            throw new InvalidArgumentException('Tenant not found');
        }

        if ($domain === '') {
            $domain = $this->tenantService->getPrimaryDomain($id) ?? '';
        }

        $provisionId = $this->provisionRepository->recordDispatch($id, (string) $tenant['name'], $domain, $adminUser);

        $result = $this->commandRunner->run(new ProvisionCommandRequest(
            tenant: (string) $tenant['name'],
            tenantDisplayName: (string) $tenant['name'],
            domain: $domain,
            adminUser: $adminUser,
            adminPassword: $adminPassword,
            provisionId: $provisionId,
            createUser: true,
            setup: true,
        ));

        if (!$result['successful']) {
            $this->logger->error('Provisioning failed for tenant {tenant}', [
                'tenant_id' => $id,
                'provision_id' => $provisionId,
                'output' => mb_substr($result['output'], 0, 2000),
            ]);
            try {
                $this->provisionRepository->markFailed($provisionId, $result['output']);
            } catch (\Throwable) {
                // Best effort
            }

            return ['ok' => false, 'provisionId' => $provisionId, 'output' => $result['output']];
        }

        $this->auditLogger->log('tenant.provision.completed', [
            'tenant_id' => $id,
            'provision_id' => $provisionId,
            'domain' => $domain,
        ]);

        return ['ok' => true, 'provisionId' => $provisionId];
    }

    public function updateBackupSchedule(int $id, int $frequency, int $retentionDays): void
    {
        $this->tenantService->updateBackupFrequency($id, $frequency);
        $this->tenantService->updateBackupRetention($id, $retentionDays);
        $this->auditLogger->log('tenant.backup_schedule.update', [
            'tenant_id' => $id,
            'backup_frequency' => $frequency,
            'backup_retention_days' => $retentionDays,
        ]);
    }

    public function queueBackup(int $id): void
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            throw new InvalidArgumentException('Tenant not found');
        }

        $this->messageBus->dispatch(new TenantBackupMessage(tenantId: $id, tenantName: (string) $tenant['name']));
    }

    public function restoreBackup(int $tenantId, int $backupId): void
    {
        $this->backupService->restore($backupId);
        $this->auditLogger->log('tenant.backup.restore', [
            'tenant_id' => $tenantId,
            'backup_id' => $backupId,
        ]);
    }

    public function deleteBackup(int $tenantId, int $backupId): void
    {
        $this->backupService->deleteBackup($backupId);
        $this->auditLogger->log('tenant.backup.delete', [
            'tenant_id' => $tenantId,
            'backup_id' => $backupId,
        ]);
    }

    public function softDeleteTenant(int $id): void
    {
        $this->tenantService->deleteTenant($id);
        $this->auditLogger->log('tenant.soft_delete', ['tenant_id' => $id]);
    }

    public function purgeTenant(int $id): void
    {
        $this->tenantService->purgeTenant($id);
        $this->auditLogger->log('tenant.purge', ['tenant_id' => $id]);
    }

    public function restoreTenant(int $id): void
    {
        $this->tenantService->restoreTenant($id);
        $this->auditLogger->log('tenant.restore', ['tenant_id' => $id]);
    }
}
