<?php

declare(strict_types=1);

namespace App\Treasury\Command;

use App\Treasury\Service\TreasuryAccountSeeder;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'treasury:accounts:seed',
    description: 'Create the treasury accounts that match the tenant\'s cash registers and bank accounts, plus suspense accounts and default mappings. Idempotent.',
)]
final class SeedTreasuryAccountsCommand extends PerTenantCommand
{
    public function __construct(
        private readonly TreasuryAccountSeeder $seeder,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->seeder->sync();

        $io->success(sprintf(
            '%d account(s) created, %d default mapping(s) set.',
            count($result['created']),
            $result['mapped'],
        ));
        if ($result['created'] !== []) {
            $io->listing($result['created']);
        }

        return Command::SUCCESS;
    }
}
