<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Application;

use App\ControlPlane\Backup\BinlogBackupService;
use App\ControlPlane\Billing\Service\PlanManager;
use App\ControlPlane\Billing\Service\TenantQuotaService;
use App\ControlPlane\Certificate\CertificateLifecycleServiceInterface;
use App\ControlPlane\Certificate\TenantCertificate;
use App\ControlPlane\Subscription\Payment\SubscriptionManager;
use App\ControlPlane\Tenant\Application\TenantService;
use App\ControlPlane\Tenant\Repository\TenantPaymentEventRepository;
use App\ControlPlane\Tenant\Repository\TenantProvisionRepository;
use App\ControlPlane\Tenant\Service\TenantBackupService;
use App\ControlPlane\Tenant\Service\TenantUsageService;
use Psr\Log\LoggerInterface;

/**
 * Aggregates tenant hub data for the operator control-plane UI.
 */
final readonly class TenantDetailQueryService
{
    public function __construct(
        private TenantService $tenantService,
        private PlanManager $planManager,
        private TenantQuotaService $tenantQuotaService,
        private TenantUsageService $tenantUsageService,
        private TenantBackupService $backupService,
        private BinlogBackupService $binlogBackupService,
        private TenantProvisionRepository $provisionRepository,
        private SubscriptionManager $subscriptionManager,
        private TenantPaymentEventRepository $paymentEventRepository,
        private CertificateLifecycleServiceInterface $certificateLifecycleService,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function getDetail(int $id): ?array
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            return null;
        }

        $tenantName = (string) ($tenant['name'] ?? '');

        return [
            'tenant' => $tenant,
            'domains' => $this->tenantService->getTenantDomains($id),
            'primaryDomain' => $this->tenantService->getPrimaryDomain($id),
            'plans' => $this->planManager->getPlans(),
            'usage' => $this->loadUsage($tenantName),
            'backups' => $this->loadBackups($id),
            'binlogSummary' => $this->loadBinlogSummary(),
            'provisions' => $this->loadProvisions($id),
            'subscription' => $this->loadSubscription($id),
            'paymentEvents' => $this->loadPaymentEvents($id),
            'certificates' => $this->loadCertificates($tenantName),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getUsage(int $id): array
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            throw new \InvalidArgumentException('Tenant not found');
        }

        return $this->loadUsage((string) ($tenant['name'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function loadUsage(string $tenantName): array
    {
        if ($tenantName === '') {
            return [
                'quotaSummary' => [],
                'usageLatest' => [],
                'usageDeltas' => [],
                'usageHistory' => [],
                'quotaAlerts' => [],
            ];
        }

        try {
            $quotaSummary = $this->tenantQuotaService->getUsageSummary($tenantName);

            return [
                'quotaSummary' => $quotaSummary,
                'usageLatest' => $this->tenantUsageService->getLatestMetrics($tenantName),
                'usageDeltas' => $this->tenantUsageService->getUsageDeltas($tenantName, 24),
                'usageHistory' => $this->tenantUsageService->getRecentHistory($tenantName, 24),
                'quotaAlerts' => $this->buildQuotaAlerts($quotaSummary),
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('Tenant detail: failed to load usage', [
                'tenant' => $tenantName,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'quotaSummary' => [],
                'usageLatest' => [],
                'usageDeltas' => [],
                'usageHistory' => [],
                'quotaAlerts' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadBackups(int $id): array
    {
        try {
            return array_values($this->backupService->listBackupsByTenantId($id));
        } catch (\Throwable $exception) {
            $this->logger->warning('Tenant detail: failed to load backups', [
                'tenant_id' => $id,
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadBinlogSummary(): array
    {
        try {
            return $this->binlogBackupService->getBinlogBackupSummary();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadProvisions(int $id): array
    {
        try {
            return array_values($this->provisionRepository->findByTenantId($id));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadSubscription(int $id): ?array
    {
        try {
            return $this->subscriptionManager->findActiveByTenant($id);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadPaymentEvents(int $id): array
    {
        try {
            return array_values($this->paymentEventRepository->findByTenantId($id));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<TenantCertificate>
     */
    private function loadCertificates(string $tenantName): array
    {
        if ($tenantName === '') {
            return [];
        }

        try {
            return $this->certificateLifecycleService->list($tenantName);
        } catch (\Throwable $exception) {
            $this->logger->warning('Tenant detail: failed to load certificates', [
                'tenant' => $tenantName,
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array<string, array{limit: int, current: int}> $quotaSummary
     * @return list<array{resource: string, percent: float}>
     */
    private function buildQuotaAlerts(array $quotaSummary): array
    {
        $alerts = [];

        foreach ($quotaSummary as $resource => $usage) {
            $limit = (int) $usage['limit'];
            $current = (int) $usage['current'];
            if ($limit <= 0) {
                continue;
            }

            $percent = ($current / $limit) * 100;
            if ($percent >= 80.0) {
                $alerts[] = [
                    'resource' => (string) $resource,
                    'percent' => round($percent, 2),
                ];
            }
        }

        usort($alerts, static fn(array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return $alerts;
    }
}
