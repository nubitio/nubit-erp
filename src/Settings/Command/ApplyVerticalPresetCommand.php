<?php

declare(strict_types=1);

namespace App\Settings\Command;

use App\Settings\Vertical\Preset\RetailPreset;
use App\Settings\Vertical\VerticalPresetApplier;
use App\Settings\Vertical\VerticalPresetPlan;
use App\Settings\Vertical\VerticalPresetPlanner;
use App\Settings\Vertical\VerticalPresetRegistry;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'tenant:apply-preset',
    description: 'Apply a vertical onboarding preset to a tenant (settings + default stock location)',
)]
class ApplyVerticalPresetCommand extends PerTenantCommand
{
    public function __construct(
        private readonly VerticalPresetRegistry $registry,
        private readonly VerticalPresetApplier $applier,
        private readonly VerticalPresetPlanner $planner,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('preset', InputArgument::OPTIONAL, 'Preset key to apply', RetailPreset::KEY);
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without applying it');
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $presetKey = (string) $input->getArgument('preset');

        if (!$this->registry->has($presetKey)) {
            $io->error(sprintf(
                'Unknown preset "%s". Available: %s.',
                $presetKey,
                implode(', ', $this->registry->keys()) ?: '(none)',
            ));

            return self::FAILURE;
        }

        try {
            $preset = $this->registry->get($presetKey);
            $plan = $this->planner->plan($preset);

            if ((bool) $input->getOption('dry-run')) {
                $this->renderPlan($io, $plan);

                return self::SUCCESS;
            }

            $result = $this->applier->apply($preset);

            $io->success(sprintf(
                'Preset "%s" v%d applied to tenant "%s"',
                $result->presetKey,
                $result->presetVersion,
                $this->currentTenantName,
            ));

            if ($result->defaultWarehouseCreated) {
                $io->writeln(' - Default warehouse created.');
            }
            foreach ($result->notes as $note) {
                $io->warning($note);
            }

            $this->logger->info('Vertical preset applied', [
                'tenant' => $this->currentTenantName,
                'preset' => $result->presetKey,
                'version' => $result->presetVersion,
                'defaultWarehouseCreated' => $result->defaultWarehouseCreated,
                'capabilitiesApplied' => $result->capabilitiesApplied,
                'settingsChanged' => array_column($plan->settingsChanges, 'field'),
                'capabilitiesEnabled' => $plan->enabledCapabilities(),
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Failed: ' . $e->getMessage());
            $this->logger->error('ApplyVerticalPreset failed', [
                'tenant' => $this->currentTenantName,
                'preset' => $presetKey,
                'exception' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    private function renderPlan(SymfonyStyle $io, VerticalPresetPlan $plan): void
    {
        $io->title(sprintf(
            'Dry run — preset "%s" v%d on "%s"',
            $plan->presetKey,
            $plan->presetVersion,
            $this->currentTenantName,
        ));

        if (!$plan->hasChanges()) {
            $io->success('No changes — the preset is already applied.');
        }

        if ($plan->settingsChanges !== []) {
            $io->section('Settings');
            $io->table(['field', 'from', 'to'], array_map(static fn(array $c): array => [
                $c['field'],
                $c['from'],
                $c['to'],
            ], $plan->settingsChanges));
        }

        if ($plan->capabilityChanges !== []) {
            $io->section('Capabilities');
            $io->table(['capability', 'change'], array_map(static fn(array $c): array => [
                $c['key'],
                $c['change'],
            ], $plan->capabilityChanges));
        }

        if ($plan->createsDefaultWarehouse) {
            $io->writeln(' - A default warehouse would be created.');
        }
        foreach ($plan->notes as $note) {
            $io->warning($note);
        }
    }
}
