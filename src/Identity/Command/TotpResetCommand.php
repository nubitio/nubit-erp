<?php

declare(strict_types=1);

namespace App\Identity\Command;

use Doctrine\DBAL\Connection;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Break-glass recovery: a user who lost both the authenticator and the recovery codes cannot sign in,
 * and if that user is the tenant's only administrator nobody in the UI can reset it. Operators run
 * this against ONE tenant to remove the second factor so the person can sign in and enrol again.
 * The password is NOT touched or bypassed.
 */
#[AsCommand(
    name: 'app:identity:totp-reset',
    description: 'Remove the second factor of a user so they can sign in with their password again (requires --tenant).',
)]
final class TotpResetCommand extends PerTenantCommand
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('username', InputArgument::REQUIRED, 'Username (not e-mail) of the locked-out user');
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = (string) $input->getArgument('username');

        $removed = $this->connection->executeStatement('DELETE FROM nubit_totp_credential WHERE user_identifier = :u', [
            'u' => $username,
        ]);
        if ($removed === 0) {
            $io->warning(sprintf(
                '"%s" has no second factor in tenant "%s": nothing to reset.',
                $username,
                $this->currentTenantName,
            ));

            return self::SUCCESS;
        }

        $this->logger->warning('Second factor removed by an operator.', [
            'tenant' => $this->currentTenantName,
            'username' => $username,
        ]);
        $io->success(sprintf(
            'Second factor removed for "%s" in tenant "%s". They can sign in with their password.',
            $username,
            $this->currentTenantName,
        ));

        return self::SUCCESS;
    }
}
