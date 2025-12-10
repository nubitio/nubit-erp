<?php

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use App\Core\Service\TenantConnectionSwitcher;
use App\Core\Service\TenantManager;
use App\Service\MenuSeedService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:menu',
    description: 'Seed menu items (idempotent).'
)]
class SeedMenuCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly MenuSeedService $menuSeedService,
        TenantManager $tenantManager,
        TenantConnectionSwitcher $tenantConnectionSwitcher,
        LoggerInterface $logger,
    ) {
        parent::__construct($tenantManager, $tenantConnectionSwitcher, $logger);
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setHelp('Seed menu items (idempotent).');
    }

    #[\Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->menuSeedService->seed();
        $io->success('Menu seeded successfully.');

        return self::SUCCESS;
    }
}
