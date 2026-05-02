<?php

declare(strict_types=1);

namespace App\Setup\Command;

use Override;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use App\Setup\Service\InitialDataSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:initial-data',
    description: 'Seed initial reference data (idempotent).',
    help: <<<'TXT'
Seed initial reference data (idempotent).
TXT
)]
class SeedInitialDataCommand extends PerTenantCommand
{
    public function __construct(
        private readonly InitialDataSeeder $initialDataSeeder,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->initialDataSeeder->seed();
        $io->success('Initial data seeded successfully.');

        return self::SUCCESS;
    }
}
