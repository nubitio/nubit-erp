<?php

declare(strict_types=1);

namespace App\Settings\Command;

use App\Settings\Vertical\Preset\RetailPreset;
use App\Settings\Vertical\VerticalPresetApplier;
use App\Settings\Vertical\VerticalPresetRegistry;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('preset', InputArgument::OPTIONAL, 'Preset key to apply', RetailPreset::KEY);
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
}
