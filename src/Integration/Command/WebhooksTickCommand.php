<?php

declare(strict_types=1);

namespace App\Integration\Command;

use App\ControlPlane\Tenant\Application\TenantService;
use App\Integration\Repository\WebhookDeliveryRepository;
use App\Integration\Webhook\WebhookDeliveryAttempt;
use App\Integration\Webhook\WebhookFanout;
use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One pass of the webhook pipeline for every tenant: drain the event outbox
 * into deliveries, then attempt every delivery whose retry time is due. Meant
 * to run once a minute from the scheduler.
 */
#[AsCommand(
    name: 'app:integration:webhooks:tick',
    description: 'Fan out pending integration events and attempt due webhook deliveries.',
)]
final class WebhooksTickCommand extends Command
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly TenantConnectionSwitcherInterface $tenantSwitcher,
        private readonly WebhookFanout $fanout,
        private readonly WebhookDeliveryRepository $deliveries,
        private readonly WebhookDeliveryAttempt $deliveryAttempt,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Restrict to a single tenant slug');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max deliveries attempted per tenant', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $only = $input->getOption('tenant');
        $limit = max(1, (int) $input->getOption('limit'));

        $tenants = $this->tenantService->getTenants();
        $failed = 0;
        $fannedOut = 0;
        $attempted = 0;

        foreach ($tenants as $tenant) {
            $name = (string) ($tenant['name'] ?? '');
            if ('' === $name || is_string($only) && '' !== $only && $only !== $name) {
                continue;
            }

            try {
                $this->tenantSwitcher->switchConnection($name);
                $fannedOut += $this->fanout->run($limit);
                foreach ($this->deliveries->claimDue(new \DateTimeImmutable(), $limit) as $delivery) {
                    $this->deliveryAttempt->attempt($delivery);
                    ++$attempted;
                }
            } catch (\Throwable $e) {
                ++$failed;
                $this->logger->error('integration.webhooks.tick_failed', [
                    'tenant' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $io->writeln(sprintf(
            'webhooks tick: fanned_out=%d attempted=%d tenant_failures=%d',
            $fannedOut,
            $attempted,
            $failed,
        ));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
