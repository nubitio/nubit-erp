<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:tenant:usage:prune',
    description: 'Prune historical tenant usage samples older than the configured retention window.',
)]
#[AsCronTask('30 2 * * *', arguments: '--days=90')]
class TenantUsagePruneCommand extends Command
{
    public function __construct(
        private readonly ConnectionRegistry $connectionRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Retention in days', '90')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only show how many rows would be removed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int)$input->getOption('days'));
        $dryRun = (bool)$input->getOption('dry-run');

        /** @var Connection $controlPlane */
        $controlPlane = $this->connectionRegistry->getConnection('control_plane');
        $cutoff = (new \DateTimeImmutable(sprintf('-%d days', $days)))->format('Y-m-d H:i:s');

        $toDelete = (int)$controlPlane->fetchOne(
            'SELECT COUNT(*) FROM tenant_usage WHERE collected_at < ?',
            [$cutoff]
        );

        if ($dryRun) {
            $io->info(sprintf(
                'Dry run: %d rows older than %s would be deleted.',
                $toDelete,
                $cutoff
            ));

            return Command::SUCCESS;
        }

        $deleted = $controlPlane->executeStatement(
            'DELETE FROM tenant_usage WHERE collected_at < ?',
            [$cutoff]
        );

        $io->success(sprintf(
            'Deleted %d row(s) from tenant_usage (cutoff: %s, previously matched: %d).',
            $deleted,
            $cutoff,
            $toDelete
        ));

        return Command::SUCCESS;
    }
}
