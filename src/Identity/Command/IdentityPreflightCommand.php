<?php

declare(strict_types=1);

namespace App\Identity\Command;

use Doctrine\DBAL\Connection;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Run BEFORE setting IDENTITY_MODULE_ENABLED=1. With the module on, every login reads
 * nubit_totp_credential; a tenant database missing that table would fail all of its logins.
 * Exits non-zero if any tenant is not ready, so it can gate a deploy.
 */
#[AsCommand(
    name: 'app:identity:preflight',
    description: 'Check that every tenant database has the identity module tables (run before enabling the module).',
)]
final class IdentityPreflightCommand extends PerTenantCommand
{
    private const array REQUIRED_TABLES = ['nubit_totp_credential', 'nubit_identity_token', 'nubit_api_key'];

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $existing = $this->connection->createSchemaManager()->listTableNames();
        $missing = array_values(array_diff(self::REQUIRED_TABLES, $existing));

        if ($missing !== []) {
            $io->error(sprintf(
                'NOT READY — tenant "%s" is missing: %s. Run app:tenants:migrate first.',
                $this->currentTenantName,
                implode(', ', $missing),
            ));

            return self::FAILURE;
        }

        $enrolled = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM nubit_totp_credential WHERE confirmed_at IS NOT NULL',
        );
        $io->writeln(sprintf(
            '<info>READY</info> tenant "%s" (users with a second factor: %d)',
            $this->currentTenantName,
            $enrolled,
        ));

        return self::SUCCESS;
    }
}
