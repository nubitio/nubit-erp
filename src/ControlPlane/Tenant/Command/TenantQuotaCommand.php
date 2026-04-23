<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use App\ControlPlane\Billing\Service\TenantQuotaService;
use App\ControlPlane\Tenant\Service\TenantManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tenant:quota',
    description: 'Display quota usage for tenants.',
)]
class TenantQuotaCommand extends Command
{
    public function __construct(
        private readonly TenantQuotaService $quotaService,
        private readonly TenantManager $tenantManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tenant', InputArgument::OPTIONAL, 'Specific tenant (shows all if omitted)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenantName = $input->getArgument('tenant');

        if ($tenantName !== null) {
            $this->showTenantQuota($io, (string) $tenantName);
        } else {
            $this->showAllTenantsQuota($io);
        }

        return Command::SUCCESS;
    }

    private function showTenantQuota(SymfonyStyle $io, string $tenantName): void
    {
        try {
            $summary = $this->quotaService->getUsageSummary($tenantName);
        } catch (\Throwable $e) {
            $io->error(sprintf('Failed to get quota for "%s": %s', $tenantName, $e->getMessage()));

            return;
        }

        $tenant = $this->tenantManager->getTenantByName($tenantName);
        $plan = (string) ($tenant['plan'] ?? 'free');

        $io->section(sprintf('Tenant: %s (plan: %s)', $tenantName, $plan));

        $rows = [];
        foreach ($summary as $resource => $data) {
            if ($resource === 'storage_bytes') {
                continue;
            }
            $limitStr = $data['limit'] === -1 ? 'unlimited' : (string) $data['limit'];
            $pct = $data['limit'] > 0
                ? sprintf('%.0f%%', ($data['current'] / $data['limit']) * 100)
                : '-';
            $rows[] = [$resource, $data['current'], $limitStr, $pct];
        }

        if (isset($summary['storage_bytes'])) {
            $storage = $summary['storage_bytes'];
            $limitStr = $storage['limit'] === -1 ? 'unlimited' : $this->formatBytes($storage['limit']);
            $pct = $storage['limit'] > 0
                ? sprintf('%.0f%%', ($storage['current'] / $storage['limit']) * 100)
                : '-';
            $rows[] = ['storage_bytes', $this->formatBytes($storage['current']), $limitStr, $pct];
        }

        $io->table(['Resource', 'Current', 'Limit', 'Usage'], $rows);
    }

    private function showAllTenantsQuota(SymfonyStyle $io): void
    {
        $tenants = $this->tenantManager->getTenants();

        if ($tenants === []) {
            $io->warning('No tenants found.');

            return;
        }

        foreach ($tenants as $tenant) {
            $this->showTenantQuota($io, (string) $tenant['name']);
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }
}
