<?php

declare(strict_types=1);

namespace App\Integration\Command;

use App\Integration\Repository\WebhookDeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Requeues every delivery of one event so the next tick retries it. Does not
 * touch the original event; use it for a lost or mis-handled webhook.
 */
#[AsCommand(
    name: 'app:integration:webhooks:replay',
    description: 'Requeue the webhook deliveries for one integration event.',
)]
final class WebhookReplayCommand extends Command
{
    public function __construct(
        private readonly TenantConnectionSwitcherInterface $tenantSwitcher,
        private readonly WebhookDeliveryRepository $deliveries,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Tenant slug')->addOption(
            'event',
            null,
            InputOption::VALUE_REQUIRED,
            'Integration event id (the uuid, without the evt_ prefix)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tenant = (string) $input->getOption('tenant');
        $eventId = (string) $input->getOption('event');
        if ('' === $tenant || '' === $eventId) {
            $io->error('--tenant and --event are required.');

            return Command::INVALID;
        }
        $eventId = str_starts_with($eventId, 'evt_') ? substr($eventId, 4) : $eventId;

        try {
            $this->tenantSwitcher->switchConnection($tenant);
        } catch (\Throwable $e) {
            $io->error(sprintf('Cannot switch to tenant "%s": %s', $tenant, $e->getMessage()));

            return Command::FAILURE;
        }

        $rows = $this->deliveries->findByEventId($eventId);
        if ([] === $rows) {
            $io->warning('No deliveries found for that event.');

            return Command::SUCCESS;
        }

        foreach ($rows as $delivery) {
            $delivery->requeue();
        }
        $this->entityManager->flush();

        $io->success(sprintf('Requeued %d deliver(ies) for event %s.', count($rows), $eventId));

        return Command::SUCCESS;
    }
}
