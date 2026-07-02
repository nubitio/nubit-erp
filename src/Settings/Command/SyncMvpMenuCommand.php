<?php

declare(strict_types=1);

namespace App\Settings\Command;

use App\Settings\Service\MenuSeedService;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:menu:sync-mvp',
    description: 'Apply the layered operational menu for existing tenants without changing tenant settings.',
)]
final class SyncMvpMenuCommand extends PerTenantCommand
{
    public function __construct(
        private readonly MenuSeedService $menuSeedService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->menuSeedService->syncMvpPreservingRoleAccess();

        $io->success('Operational menu synchronized. Tenant settings were not changed.');

        return Command::SUCCESS;
    }
}
