<?php

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

    abstract protected function executeTenantCommand(
        InputInterface $input,
        OutputInterface $output,
    ): int;
}
