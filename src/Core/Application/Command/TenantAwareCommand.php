<?php

declare(strict_types=1);

namespace App\Core\Application\Command;

use Override;
use Exception;
use App\Core\Exception\ServiceException;
use App\Core\Service\TenantConnectionSwitcher;
use App\Core\Service\TenantManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

abstract class TenantAwareCommand extends Command
{
    use LockableTrait;

    protected array $tenants = [];

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly TenantConnectionSwitcher $tenantConnectionSwitcher,
        protected readonly LoggerInterface $logger,
    ) {
        parent::__construct();
        $this->tenants = $this->tenantManager->getTenants();
    }

    #[Override]
    protected function configure(): void
    {
        $this->setHelp('This command allows you to execute tenant command');
        $this->addOption(
            name: 'tenant',
            shortcut: 't',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Execute command for a specific tenant',
        );
        $this->addOption(
            name: 'parallel',
            shortcut: 'p',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Run for all tenants in parallel (value = max concurrency, default 4)',
            default: false,
        );
    }

    #[Override]
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        if (!$this->lock()) {
            $this->logger->info(
                'The command is already running in another process.'
            );
            $output->writeln(
                'The command is already running in another process.'
            );

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);

        if ($this->tenants === []) {
            $this->logger->warning('No tenants found');
            $io->warning('No tenants found');
            $io->info('Using default connection');
            $this->executeTenantCommand($input, $output);
            $io->success('Done!');
            $this->release();

            return Command::SUCCESS;
        }

        if ($tenantName = $input->getOption('tenant')) {
            $this->tenantConnectionSwitcher->switchConnection($tenantName);
            $io->title('Tenant: ' . $tenantName);
            $this->executeTenantCommand($input, $output);
            $io->success('Done!');
            $this->release();

            return Command::SUCCESS;
        }

        $parallel = $input->getOption('parallel');
        if ($parallel !== false) {
            $concurrency = (int)($parallel ?: 4);

            return $this->executeAllTenantsParallel($io, $concurrency);
        }

        return $this->executeAllTenants($io, $input, $output);
    }

    private function executeAllTenants(
        SymfonyStyle $io,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        foreach ($this->tenants as $tenant) {
            try {
                $tenantName = $tenant['name'];
                $this->tenantConnectionSwitcher->switchConnection($tenantName);
                $io->title('Tenant: ' . $tenantName);

                $this->executeTenantCommand($input, $output);
            } catch (ServiceException | \Doctrine\DBAL\Driver\Exception | \Doctrine\DBAL\Exception | Exception $e) {
                $this->logger->error(
                    'An error occurred while executing command for tenant '
                    . $tenantName,
                    ['exception' => $e]
                );
                $io->error(
                    'An error occurred while executing command for tenant '
                    . $tenantName
                );
            }
        }

        $io->success('Done!');

        $this->release();

        return Command::SUCCESS;
    }

    private function executeAllTenantsParallel(SymfonyStyle $io, int $concurrency): int
    {
        /** @var string $commandName */
        $commandName = $this->getName();
        $io->info(sprintf(
            'Running "%s" for %d tenants (concurrency: %d)',
            $commandName,
            count($this->tenants),
            $concurrency
        ));

        /** @var array<string, Process> $running */
        $running = [];
        $queue = array_map(static fn (array $t): string => (string)$t['name'], $this->tenants);
        $failed = [];
        $succeeded = [];

        while ($queue !== [] || $running !== []) {
            while (count($running) < $concurrency && $queue !== []) {
                $tenantName = array_shift($queue);
                $process = new Process([PHP_BINARY, 'bin/console', $commandName, '--tenant=' . $tenantName]);
                $process->setTimeout(null);
                $process->start();
                $running[$tenantName] = $process;
                $io->writeln(sprintf('  <info>Started</info> %s', $tenantName));
            }

            foreach ($running as $tenantName => $process) {
                if (!$process->isRunning()) {
                    if ($process->isSuccessful()) {
                        $succeeded[] = $tenantName;
                        $io->writeln(sprintf('  <fg=green>Done</> %s', $tenantName));
                    } else {
                        $failed[] = $tenantName;
                        $this->logger->error('Parallel execution failed for tenant', [
                            'tenant' => $tenantName,
                            'output' => $process->getErrorOutput(),
                        ]);
                        $io->writeln(sprintf('  <fg=red>Failed</> %s', $tenantName));
                    }

                    unset($running[$tenantName]);
                }
            }

            if ($running !== []) {
                usleep(100_000); // 100ms poll
            }
        }

        $io->newLine();
        $io->writeln(sprintf('Succeeded: %d, Failed: %d', count($succeeded), count($failed)));

        if ($failed !== []) {
            $io->error('Failed tenants: ' . implode(', ', $failed));
            $this->release();

            return Command::FAILURE;
        }

        $io->success('All tenants processed');
        $this->release();

        return Command::SUCCESS;
    }

    abstract protected function executeTenantCommand(
        InputInterface $input,
        OutputInterface $output,
    ): int;
}
