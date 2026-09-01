<?php

declare(strict_types=1);

namespace App\ControlPlane\Billing\Command;

use App\ControlPlane\Billing\Feature\FeatureCatalog;
use App\ControlPlane\Billing\Feature\PlanFeatureManager;
use App\ControlPlane\Billing\Repository\PlanFeatureRepositoryInterface;
use App\ControlPlane\Billing\Service\PlanManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reconciles the plan_features table with {@see FeatureCatalog}.
 *
 * Default run: gap-fill only — inserts a row (with the catalogue's per-plan
 * default) for every (plan × feature) pair that has none, and never touches an
 * existing row. Run it after control-plane migrations on every deploy so a
 * newly-added catalogue feature lands on every plan without a migration.
 *
 * --force: also overwrite existing rows back to the catalogue default (a
 * deliberate reset; discards operator customisations).
 */
#[AsCommand(
    name: 'app:features:sync',
    description: 'Reconcile plan_features with the FeatureCatalog (gap-fill; --force to reset).',
)]
final class SyncPlanFeaturesCommand extends Command
{
    public function __construct(
        private readonly PlanManager $planManager,
        private readonly PlanFeatureManager $planFeatureManager,
        private readonly PlanFeatureRepositoryInterface $planFeatureRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing rows with the catalogue default (discards operator changes).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force') === true;

        $plans = $this->planManager->getPlans();
        if ($plans === []) {
            $io->warning('No plans found — nothing to sync.');

            return Command::SUCCESS;
        }

        $added = 0;
        $reset = 0;

        foreach ($plans as $plan) {
            $planName = $plan['name'];
            foreach (FeatureCatalog::keys() as $featureKey) {
                $existing = $this->planFeatureRepository->findOneByPlanAndKey($planName, $featureKey);

                if ($existing !== null && !$force) {
                    continue;
                }

                $default = FeatureCatalog::planDefault($featureKey, $planName);
                $this->planFeatureManager->setFeature($planName, $featureKey, $default['enabled'], $default['config']);

                $existing === null ? ++$added : ++$reset;
            }
        }

        $io->success(sprintf(
            '%d plan(s) × %d feature(s): %d row(s) added%s.',
            count($plans),
            count(FeatureCatalog::keys()),
            $added,
            $force ? sprintf(', %d reset to catalogue default', $reset) : '',
        ));

        return Command::SUCCESS;
    }
}
