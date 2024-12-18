<?php

namespace App\Core\Application\Command;

use App\Core\Infrastructure\Service\TenantConnectionSwitcher;
use App\Core\Infrastructure\Service\TenantManager;
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
    ) {
        parent::__construct();
        $this->tenants = $this->tenantManager->getTenants();
    }

    protected function configure(): void
    {
        $this->setHelp('This command allows you to execute tenant command');
        $this->addOption(
            name: 'tenant',
            shortcut: 't',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Execute command for a specific tenant',
            suggestedValues: array_column($this->tenants, 'name'),
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);

        if (empty($this->tenants)) {
            $io->warning('No tenants found');

            return Command::SUCCESS;
        }

        if ($tenantName = $input->getOption('tenant')) {
            $this->tenantConnectionSwitcher->switchConnection($tenantName);
            $io->title('Tenant: '.$tenantName);

            $this->executeTenantCommand($input, $output);

            $io->success('Done!');

            $this->release();

            return Command::SUCCESS;
        } else {
            return $this->executeAllTenants($io, $input, $output);
        }
    }

    private function executeAllTenants(SymfonyStyle $io, InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->tenants as $tenant) {
            $tenantName = $tenant['name'];
            $this->tenantConnectionSwitcher->switchConnection($tenantName);
            $io->title('Tenant: '.$tenantName);

            $this->executeTenantCommand($input, $output);
        }

        $io->success('Done!');

        $this->release();

        return Command::SUCCESS;
    }

    abstract protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int;
}
