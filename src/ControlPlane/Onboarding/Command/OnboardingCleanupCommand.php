<?php

declare(strict_types=1);

namespace App\ControlPlane\Onboarding\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:onboarding:cleanup', description: 'Clean up unverified accounts and stale provisioning records')]
final class OnboardingCleanupCommand extends Command
{
    public function __construct(
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'hours',
            null,
            InputOption::VALUE_REQUIRED,
            'Delete unverified accounts older than N hours',
            '24',
        )->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only show what would be deleted');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hours = max(1, (int) $input->getOption('hours'));
        $dryRun = (bool) $input->getOption('dry-run');
        $conn = $this->cp();

        $cutoff = new \DateTimeImmutable(sprintf('-%d hours', $hours))->format('Y-m-d H:i:s');

        // Count unverified accounts to clean
        $unverifiedCount = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM accounts WHERE verified_at IS NULL AND created_at < ?',
            [$cutoff],
            [ParameterType::STRING],
        );

        // Count stale failed provisions (older than cutoff, no tenant created)
        $staleProvisionCount = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM tenant_provisions WHERE status = 'failed' AND tenant_id IS NULL AND created_at < ?",
            [$cutoff],
            [ParameterType::STRING],
        );

        $io->writeln(sprintf('Unverified accounts older than %d hours: %d', $hours, $unverifiedCount));
        $io->writeln(sprintf('Failed provisions (no tenant) older than %d hours: %d', $hours, $staleProvisionCount));

        if ($dryRun) {
            $io->note('Dry run - no changes made.');

            return Command::SUCCESS;
        }

        $deletedAccounts = 0;
        $deletedProvisions = 0;

        if ($unverifiedCount > 0) {
            $deletedAccounts = $conn->executeStatement(
                'DELETE FROM accounts WHERE verified_at IS NULL AND created_at < ?',
                [$cutoff],
                [ParameterType::STRING],
            );

            $this->logger->info('Cleaned up unverified accounts', ['count' => $deletedAccounts]);
        }

        if ($staleProvisionCount > 0) {
            $deletedProvisions = $conn->executeStatement(
                "DELETE FROM tenant_provisions WHERE status = 'failed' AND tenant_id IS NULL AND created_at < ?",
                [$cutoff],
                [ParameterType::STRING],
            );

            $this->logger->info('Cleaned up stale provisions', ['count' => $deletedProvisions]);
        }

        $io->success(sprintf('Deleted %d account(s) and %d provision(s).', $deletedAccounts, $deletedProvisions));

        return Command::SUCCESS;
    }

    private function cp(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->connectionRegistry->getConnection('control_plane');

        return $connection;
    }
}
