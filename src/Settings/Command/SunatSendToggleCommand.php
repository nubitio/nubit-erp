<?php

declare(strict_types=1);

namespace App\Settings\Command;

use App\Settings\Service\SunatStateService;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sunat:send:toggle',
    description: 'Enable or pause SUNAT sending for all tenants (or a specific tenant)',
)]
class SunatSendToggleCommand extends PerTenantCommand
{
    public function __construct(
        private readonly SunatStateService $sunatStateService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('pause', null, InputOption::VALUE_NONE, 'Pause SUNAT sending (default: resume/enable)');
        $this->addOption('reset-streak', null, InputOption::VALUE_NONE, 'Also reset the rejection streak counter');
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pause = (bool) $input->getOption('pause');
        $resetStreak = (bool) $input->getOption('reset-streak');

        try {
            $state = $this->sunatStateService->get();
            $state->setSendEnabled(!$pause);

            if ($resetStreak || !$pause) {
                $state->resetStreak();
            }

            $this->sunatStateService->save($state);

            $action = $pause ? 'paused' : 'enabled';
            $streakNote = $resetStreak || !$pause ? ', streak reset to 0' : '';
            $io->success(sprintf('SUNAT sending %s for tenant "%s"%s', $action, $this->currentTenantName, $streakNote));

            $this->logger->info('SUNAT sending toggled', [
                'tenant' => $this->currentTenantName,
                'sendEnabled' => !$pause,
                'rejectionStreak' => $state->getRejectionStreak(),
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Failed: ' . $e->getMessage());
            $this->logger->error('SunatSendToggle failed', [
                'tenant' => $this->currentTenantName,
                'exception' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
