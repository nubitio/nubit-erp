<?php

declare(strict_types=1);

namespace App\Integration\Command;

use App\Integration\Entity\ApiClient;
use App\Integration\Entity\ApiKey;
use App\Integration\Sales\SalesClientAccess;
use App\Integration\Security\ApiClientUser;
use App\Integration\Security\ApiKeyGenerator;
use App\Organization\Entity\Branch;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mints an integration API client + its first key for a tenant. The full key
 * is printed once and never recoverable afterwards.
 *
 *   bin/console app:integration:api-key:create \
 *       --tenant=nubit --name="nubit-control - facturación" \
 *       --scope=documents:write --scope=documents:read
 */
#[AsCommand(
    name: 'app:integration:api-key:create',
    description: 'Create an integration API client and its first key for a tenant.',
)]
final class ApiKeyCreateCommand extends Command
{
    public function __construct(
        private readonly ApiKeyGenerator $keys,
        private readonly TenantConnectionSwitcherInterface $tenantSwitcher,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Tenant slug (subdomain)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Human label for the client')
            ->addOption(
                'scope',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Scope, repeatable',
                [],
            )
            ->addOption('sales-branch', null, InputOption::VALUE_REQUIRED, 'Branch ID for commercial sales scopes')
            ->addOption('test', null, InputOption::VALUE_NONE, 'Mint a test key (nb_test_…) instead of live');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tenant = (string) $input->getOption('tenant');
        $name = (string) $input->getOption('name');

        $scopeOption = $input->getOption('scope');
        $scopes = [];
        if (is_array($scopeOption)) {
            foreach ($scopeOption as $scope) {
                $scope = trim((string) $scope);
                if ('' !== $scope) {
                    $scopes[] = $scope;
                }
            }
        }

        if ('' === $tenant || '' === $name || [] === $scopes) {
            $io->error('--tenant, --name and at least one --scope are required.');

            return Command::INVALID;
        }
        $salesBranchId = trim((string) $input->getOption('sales-branch'));
        $hasSalesScope =
            in_array(SalesClientAccess::WRITE_SCOPE, $scopes, strict: true)
            || in_array(SalesClientAccess::READ_SCOPE, $scopes, strict: true)
            || in_array(SalesClientAccess::PAYMENT_SCOPE, $scopes, strict: true);
        if ($hasSalesScope !== ($salesBranchId !== '')) {
            $io->error(
                '--sales-branch is required with sales:read, sales:write or payments:write and requires at least one of those scopes.',
            );

            return Command::INVALID;
        }

        try {
            $this->tenantSwitcher->switchConnection($tenant);
        } catch (\Throwable $e) {
            $io->error(sprintf('Cannot switch to tenant "%s": %s', $tenant, $e->getMessage()));

            return Command::FAILURE;
        }

        $client = new ApiClient($name, $scopes);
        if ($salesBranchId !== '') {
            $branch = $this->entityManager->find(Branch::class, $salesBranchId);
            if (!$branch instanceof Branch || !$branch->getIsActive() || ($branchId = $branch->getId()) === null) {
                $io->error('The tenant has no active branch with that ID.');

                return Command::INVALID;
            }
            $client->assignSalesBranch($branchId);
        }

        $material = $this->keys->generate($tenant, true === $input->getOption('test'));
        new ApiKey($client, $material->prefix, $material->tokenHash);

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        $io->success('API client created.');
        $io->definitionList(
            ['Client' => $name],
            ['Tenant' => $tenant],
            ['Scopes' => implode(', ', $scopes)],
            ['Roles' => implode(', ', array_map(ApiClientUser::roleForScope(...), $scopes))],
        );
        $io->writeln('  <comment>Copy the key now — it will not be shown again:</comment>');
        $io->writeln('');
        $io->writeln('    ' . $material->secret);
        $io->writeln('');

        return Command::SUCCESS;
    }
}
