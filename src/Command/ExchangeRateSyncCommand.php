<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ExchangeRateSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Cron (run via system cron if symfony/scheduler is not available):
//   0 9 * * 1-6  /app/bin/console app:exchange-rate:sync --env=prod
#[AsCommand(
    name: 'app:exchange-rate:sync',
    description: 'Sync USD/PEN exchange rate from BCRP API.',
)]
class ExchangeRateSyncCommand extends Command
{
    public function __construct(
        private readonly ExchangeRateSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->syncService->sync();

        if ($result['success']) {
            $io->success(sprintf(
                'Exchange rate updated: 1 USD = %s PEN (as of %s UTC)',
                $result['rate'],
                $result['updated_at'],
            ));
        } else {
            $io->warning(sprintf('Could not sync from BCRP: %s', $result['message']));
        }

        return Command::SUCCESS;
    }
}
