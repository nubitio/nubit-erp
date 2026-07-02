<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Command;

use App\ControlPlane\Billing\Entity\TenantFeatureOverride;
use App\ControlPlane\Billing\Repository\TenantFeatureOverrideRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/**
 * Soft-marks expired feature overrides as cleaned so they are no longer
 * returned by the active-override queries.
 *
 * AC-7: Expired overrides are ignored — this command marks them as cleaned
 *       so the resolution logic falls through to the plan-level feature.
 *
 * Runs daily at 03:15 UTC. Can also be triggered manually.
 */
#[AsCommand(
    name: 'app:feature-overrides:clean-expired',
    description: 'Soft-mark expired tenant feature overrides as cleaned.',
)]
#[AsCronTask('15 3 * * *')]
final class CleanExpiredFeatureOverridesCommand extends Command
{
    private readonly Connection $cp;

    public function __construct(
        private readonly TenantFeatureOverrideRepositoryInterface $repository,
        ConnectionRegistry $connectionRegistry,
    ) {
        parent::__construct();

        /** @var Connection $cp */
        $cp = $connectionRegistry->getConnection('control_plane');
        $this->cp = $cp;
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Show how many overrides would be cleaned without actually updating them.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $expired = $this->repository->findExpiredUncleaned();

        if ($expired === []) {
            $io->success('No expired feature overrides to clean.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->info(sprintf('Dry run: %d expired feature override(s) would be marked as cleaned.', count($expired)));

            return Command::SUCCESS;
        }

        $now = new \DateTimeImmutable()->format('Y-m-d H:i:s');
        $cleaned = 0;

        foreach ($expired as $override) {
            $affected = $this->cp->update('tenant_feature_overrides', ['cleaned_at' => $now], ['id' => $override->id]);

            if ($affected > 0) {
                ++$cleaned;
            }
        }

        $io->success(sprintf(
            'Marked %d expired feature override(s) as cleaned (out of %d matched).',
            $cleaned,
            count($expired),
        ));

        return Command::SUCCESS;
    }
}
