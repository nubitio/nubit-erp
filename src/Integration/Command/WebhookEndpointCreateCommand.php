<?php

declare(strict_types=1);

namespace App\Integration\Command;

use App\Integration\Event\EventType;
use App\Integration\Webhook\WebhookEndpoint;
use App\Integration\Webhook\WebhookSecretGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Registers a webhook endpoint for a tenant and prints its signing secret once.
 *
 *   bin/console app:integration:webhook-endpoint:create \
 *       --tenant=nubit --url=https://nubit.pe/api/fiscal/efact/webhook \
 *       --type=document.*
 */
#[AsCommand(name: 'app:integration:webhook-endpoint:create', description: 'Register a webhook endpoint for a tenant.')]
final class WebhookEndpointCreateCommand extends Command
{
    public function __construct(
        private readonly TenantConnectionSwitcherInterface $tenantSwitcher,
        private readonly WebhookSecretGenerator $secrets,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Tenant slug')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'HTTPS destination URL')
            ->addOption(
                'type',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Subscribed type or pattern, repeatable',
                [],
            )
            ->addOption('secret', null, InputOption::VALUE_REQUIRED, 'Use this secret instead of a generated one');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tenant = (string) $input->getOption('tenant');
        $url = (string) $input->getOption('url');
        if ('' === $tenant || !str_starts_with($url, 'https://')) {
            $io->error('--tenant is required and --url must be an https URL.');

            return Command::INVALID;
        }

        $types = [];
        $typeOption = $input->getOption('type');
        if (is_array($typeOption)) {
            foreach ($typeOption as $type) {
                $type = trim((string) $type);
                if ('' !== $type) {
                    $types[] = $type;
                }
            }
        }
        if ([] === $types) {
            $types = ['*'];
        }

        try {
            $this->tenantSwitcher->switchConnection($tenant);
        } catch (\Throwable $e) {
            $io->error(sprintf('Cannot switch to tenant "%s": %s', $tenant, $e->getMessage()));

            return Command::FAILURE;
        }

        $secretOption = $input->getOption('secret');
        $secret = is_string($secretOption) && '' !== $secretOption ? $secretOption : $this->secrets->generate();

        $endpoint = new WebhookEndpoint($url, $secret, $types);
        $this->entityManager->persist($endpoint);
        $this->entityManager->flush();

        $io->success('Webhook endpoint registered.');
        $io->definitionList(
            ['Tenant' => $tenant],
            ['URL' => $url],
            ['Types' => implode(', ', $types)],
            ['Known event types' => implode(', ', EventType::all())],
        );
        $io->writeln('  <comment>Signing secret (shown once):</comment>');
        $io->writeln('');
        $io->writeln('    ' . $secret);
        $io->writeln('');

        return Command::SUCCESS;
    }
}
