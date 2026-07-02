<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:control-plane:user:create', description: 'Create or update a control plane admin user.')]
final class ControlPlaneUserCreateCommand extends Command
{
    public function __construct(
        private readonly ConnectionRegistry $connectionRegistry,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Control plane username')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'User password (if omitted, prompt hidden)')
            ->addOption(
                'roles',
                null,
                InputOption::VALUE_OPTIONAL,
                'Comma-separated roles (default: ROLE_ADMIN)',
                'ROLE_ADMIN',
            )
            ->addOption('inactive', null, InputOption::VALUE_NONE, 'Create or update user as inactive');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = strtolower(trim((string) $input->getArgument('username')));
        if ($username === '') {
            $io->error('Username is required.');

            return Command::FAILURE;
        }
        if (!preg_match('/^[a-z0-9._-]{3,64}$/', $username)) {
            $io->error('Username must be 3-64 chars and only use [a-z0-9._-].');

            return Command::FAILURE;
        }

        $password = trim((string) $input->getOption('password'));
        if ($password === '') {
            if (!$input->isInteractive()) {
                $io->error('Password is required in non-interactive mode. Use --password.');

                return Command::FAILURE;
            }

            $password = (string) $io->askHidden('Password');
        }
        if (mb_strlen($password) < 10) {
            $io->error('Password must be at least 10 characters.');

            return Command::FAILURE;
        }

        $rolesRaw = (string) $input->getOption('roles');
        $roles = array_values(array_filter(
            array_map('trim', explode(',', $rolesRaw)),
            static fn(string $role): bool => $role !== '',
        ));
        if ($roles === []) {
            $roles = ['ROLE_ADMIN'];
        }

        $isActive = !$input->getOption('inactive');
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $conn = $this->getControlPlaneConnection();
        if (!$conn->createSchemaManager()->tablesExist(['control_plane_admin_users'])) {
            $io->error('control_plane_admin_users table does not exist. Run control-plane migrations first.');

            return Command::FAILURE;
        }

        $conn->executeStatement(
            <<<'SQL'
                    INSERT INTO control_plane_admin_users
                        (username, password_hash, roles, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        password_hash = VALUES(password_hash),
                        roles = VALUES(roles),
                        is_active = VALUES(is_active),
                        updated_at = NOW()
                SQL,
            [$username, $hash, json_encode($roles), $isActive ? 1 : 0],
        );

        $io->success(sprintf('Control plane user "%s" saved (%s).', $username, $isActive ? 'active' : 'inactive'));

        return Command::SUCCESS;
    }

    private function getControlPlaneConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }
}
