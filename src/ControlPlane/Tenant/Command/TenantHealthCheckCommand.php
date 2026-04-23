<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use App\ControlPlane\Tenant\Service\TenantHealthChecker;
use App\ControlPlane\Tenant\Service\TenantManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tenant:health',
    description: 'Check the health of tenant databases.',
)]
class TenantHealthCheckCommand extends Command
{
    public function __construct(
        private readonly TenantHealthChecker $healthChecker,
        private readonly TenantManager $tenantManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'tenant',
            InputArgument::OPTIONAL,
            'Specific tenant to check (checks all if omitted)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenantName = $input->getArgument('tenant');
        $hasFailures = false;

        if ($tenantName !== null) {
            $result = $this->healthChecker->check((string) $tenantName);
            $this->renderResult($io, $result);
            $hasFailures = !$result['healthy'];
        } else {
            $tenants = $this->tenantManager->getTenants();

            if ($tenants === []) {
                $io->warning('No tenants found');

                return Command::SUCCESS;
            }

            foreach ($tenants as $tenant) {
                $result = $this->healthChecker->check((string) $tenant['name']);
                $this->renderResult($io, $result);

                if (!$result['healthy']) {
                    $hasFailures = true;
                }
            }
        }

        if ($hasFailures) {
            $io->error('Some tenants have health issues');

            return Command::FAILURE;
        }

        $io->success('All tenants healthy');

        return Command::SUCCESS;
    }

    /**
     * @param array{healthy: bool, tenant: string, checks: array<string, array{ok: bool, message: string}>} $result
     */
    private function renderResult(SymfonyStyle $io, array $result): void
    {
        $io->section(sprintf('Tenant: %s', $result['tenant']));

        $rows = [];
        foreach ($result['checks'] as $check => $status) {
            $rows[] = [
                $check,
                $status['ok'] ? '<fg=green>OK</>' : '<fg=red>FAIL</>',
                $status['message'],
            ];
        }

        $io->table(['Check', 'Status', 'Message'], $rows);
    }
}
