<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use App\ControlPlane\Tenant\Application\TenantService;
use App\ControlPlane\Tenant\Service\TenantUsageService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:tenant:usage', description: 'Collect and display usage metrics for tenants.')]
class TenantUsageCommand extends Command
{
    public function __construct(
        private readonly TenantUsageService $usageService,
        private readonly TenantService $tenantService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tenant', InputArgument::OPTIONAL, 'Specific tenant (checks all if omitted)')->addOption(
            'store',
            null,
            InputOption::VALUE_NONE,
            'Store metrics in control plane for historical tracking',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenantName = $input->getArgument('tenant');
        $store = (bool) $input->getOption('store');

        if ($tenantName !== null) {
            $this->processOneTenant($io, (string) $tenantName, $store);
        } else {
            $tenants = $this->tenantService->getTenants();

            if ($tenants === []) {
                $io->warning('No tenants found');

                return Command::SUCCESS;
            }

            $summaryRows = [];
            foreach ($tenants as $tenant) {
                $name = (string) $tenant['name'];
                $metrics = $this->collectSafe($io, $name);
                if ($metrics === null) {
                    continue;
                }

                if ($store) {
                    $this->usageService->storeMetrics($name, $metrics);
                }

                $summaryRows[] = [
                    $name,
                    $metrics['users'] ?? 0,
                    $metrics['customers'] ?? 0,
                    $metrics['products'] ?? 0,
                    $metrics['invoices'] ?? 0,
                    $this->formatBytes($metrics['storage_bytes'] ?? 0),
                ];
            }

            $io->table(['Tenant', 'Users', 'Customers', 'Products', 'Invoices', 'Storage'], $summaryRows);
        }

        if ($store) {
            $io->info('Metrics stored in control plane');
        }

        $io->success('Done');

        return Command::SUCCESS;
    }

    private function processOneTenant(SymfonyStyle $io, string $tenantName, bool $store): void
    {
        $metrics = $this->collectSafe($io, $tenantName);
        if ($metrics === null) {
            return;
        }

        if ($store) {
            $this->usageService->storeMetrics($tenantName, $metrics);
        }

        $rows = [];
        foreach ($metrics as $metric => $value) {
            $displayValue = $metric === 'storage_bytes' ? $this->formatBytes($value) : (string) $value;
            $rows[] = [$metric, $displayValue];
        }

        $io->section('Tenant: ' . $tenantName);
        $io->table(['Metric', 'Value'], $rows);
    }

    /**
     * @return array<string, int>|null
     */
    private function collectSafe(SymfonyStyle $io, string $tenantName): ?array
    {
        try {
            return $this->usageService->collectMetrics($tenantName);
        } catch (\Throwable $e) {
            $io->warning(sprintf('Failed to collect metrics for "%s": %s', $tenantName, $e->getMessage()));

            return null;
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 0) {
            return 'n/a';
        }

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }
}
