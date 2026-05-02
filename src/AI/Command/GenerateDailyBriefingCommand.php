<?php

declare(strict_types=1);

namespace App\AI\Command;

use App\AI\Briefing\DailyBriefingService;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generates the AI-narrated daily morning briefing.
 *
 * Usage:
 *   php bin/console app:ai:briefing:generate              # all tenants (sequential)
 *   php bin/console app:ai:briefing:generate --tenant=foo # single tenant
 *   php bin/console app:ai:briefing:generate --parallel   # all tenants in parallel
 */
#[AsCommand(
    name: 'app:ai:briefing:generate',
    description: 'Generate the AI-narrated daily morning briefing for all (or one) tenants',
)]
final class GenerateDailyBriefingCommand extends PerTenantCommand
{
    public function __construct(
        private readonly DailyBriefingService $briefingService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->setHelp(
            <<<'HELP'
            Generates an AI-narrated morning briefing for every active tenant (or one
            when --tenant is given). The result is cached in Redis for 20 hours under
            the key <info>briefing.{tenantName}.{Y-m-d}</info>.

            This command is wired to the Symfony Scheduler and runs automatically
            every day at 07:30 America/Lima.
            HELP
        );
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenantName = (string) ($this->currentTenantName ?? 'default');

        try {
            $briefing = $this->briefingService->generate($tenantName);

            if ($briefing === DailyBriefingService::DEFAULT_MESSAGE) {
                $io->warning(sprintf('[WARN] No sufficient data for tenant "%s" — default message stored.', $tenantName));
            } else {
                $io->success(sprintf('[OK] Briefing generated for tenant %s', $tenantName));
                $io->text($briefing);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('GenerateDailyBriefingCommand: failed for tenant', [
                'tenant'    => $tenantName,
                'exception' => $e->getMessage(),
            ]);
            $io->error(sprintf('[ERROR] Failed for %s: %s', $tenantName, $e->getMessage()));

            return self::FAILURE;
        }
    }
}
