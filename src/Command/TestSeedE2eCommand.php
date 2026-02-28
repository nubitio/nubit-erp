<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\E2eTestSeedService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test:seed-e2e',
    description: 'Seeds deterministic E2E credentials and sale data for CI/testing.',
)]
final class TestSeedE2eCommand extends Command
{
    public function __construct(
        private readonly E2eTestSeedService $seedService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ((string) ($_SERVER['E2E_SEED_ENABLED'] ?? '') !== '1') {
            $output->writeln('<error>app:test:seed-e2e requires E2E_SEED_ENABLED=1.</error>');

            return Command::FAILURE;
        }

        $payload = $this->seedService->seed();

        $output->writeln((string) json_encode($payload, JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
