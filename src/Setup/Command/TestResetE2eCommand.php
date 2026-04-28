<?php

declare(strict_types=1);

namespace App\Setup\Command;

use App\Setup\Service\E2eTestSeedServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test:reset-e2e',
    description: 'Cleans E2E credentials and seeded sales used in CI/testing.',
)]
final class TestResetE2eCommand extends Command
{
    public function __construct(
        private readonly E2eTestSeedServiceInterface $seedService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        unset($input);

        if ((string) ($_SERVER['E2E_SEED_ENABLED'] ?? '') !== '1') {
            $output->writeln('<error>app:test:reset-e2e requires E2E_SEED_ENABLED=1.</error>');

            return Command::FAILURE;
        }

        $this->seedService->reset();
        $output->writeln('{"status":"ok"}');

        return Command::SUCCESS;
    }
}
