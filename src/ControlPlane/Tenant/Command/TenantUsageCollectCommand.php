<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use App\ControlPlane\Billing\Service\TenantQuotaService;
use App\ControlPlane\Tenant\Application\TenantService;
use App\ControlPlane\Tenant\Service\TenantUsageService;
use App\Efact\Audit\AuditLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(name: 'app:tenant:usage:collect', description: 'Collect and store usage metrics for all active tenants.')]
#[AsCronTask('15 * * * *')]
final class TenantUsageCollectCommand extends Command
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly TenantUsageService $usageService,
        private readonly TenantQuotaService $tenantQuotaService,
        private readonly AuditLogger $auditLogger,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenants = $this->tenantService->getTenants();

        if ($tenants === []) {
            $io->warning('No tenants found.');

            return Command::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            $tenantName = (string) ($tenant['name'] ?? '');
            if ($tenantName === '') {
                continue;
            }

            try {
                $metrics = $this->usageService->collectMetrics($tenantName);
                $this->usageService->storeMetrics($tenantName, $metrics);
                $this->emitQuotaAlerts($tenantName);
                ++$processed;
            } catch (\Throwable $e) {
                ++$failed;
                $this->logger->warning('Tenant usage collection failed', [
                    'tenant' => $tenantName,
                    'exception' => $e,
                ]);
            }
        }

        $io->success(sprintf('Usage collection completed. processed=%d failed=%d', $processed, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function emitQuotaAlerts(string $tenantName): void
    {
        $summary = $this->tenantQuotaService->getUsageSummary($tenantName);

        foreach ($summary as $resource => $usage) {
            $limit = (int) $usage['limit'];
            $current = (int) $usage['current'];
            if ($limit <= 0) {
                continue;
            }

            $percent = ($current / $limit) * 100;
            if ($percent < 80.0) {
                continue;
            }

            $context = [
                'tenant' => $tenantName,
                'resource' => $resource,
                'current' => $current,
                'limit' => $limit,
                'percent' => round($percent, 2),
            ];

            if ($percent >= 100.0) {
                $this->logger->error('Tenant quota exceeded', $context);
                $this->auditLogger->log('tenant.quota.exceeded', $context);
                continue;
            }

            $this->logger->warning('Tenant quota threshold reached', $context);
            $this->auditLogger->log('tenant.quota.threshold', $context);
        }
    }
}
