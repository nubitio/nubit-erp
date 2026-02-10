<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Service\TenantConnectionSwitcher;
use App\Dto\Setup\InitialSetupInput;
use App\Setup\InitialSetupService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:tenant:provision',
    description: 'Provision a tenant locally (DB, control plane, migrations, optional seed).',
)]
class TenantProvisionCommand extends Command
{
    public function __construct(
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly InitialSetupService $initialSetupService,
        private readonly TenantConnectionSwitcher $tenantConnectionSwitcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'Tenant name (slug)')
            ->addOption('domain', null, InputOption::VALUE_OPTIONAL, 'Primary domain for tenant')
            ->addOption('create-user', null, InputOption::VALUE_NONE, 'Create DB user equal to tenant name')
            ->addOption('db-user-pass', null, InputOption::VALUE_OPTIONAL, 'DB user password (defaults to Admin.3000)')
            ->addOption('provisioning-url', null, InputOption::VALUE_OPTIONAL, 'Provisioning database URL')
            ->addOption('seed-initial', null, InputOption::VALUE_NONE, 'Run initial data seed')
            ->addOption('seed-test', null, InputOption::VALUE_NONE, 'Run test data seed')
            ->addOption('setup', null, InputOption::VALUE_NONE, 'Run initial setup (admin, company, branch)')
            ->addOption('admin-user', null, InputOption::VALUE_OPTIONAL, 'Admin username')
            ->addOption('admin-pass', null, InputOption::VALUE_OPTIONAL, 'Admin password')
            ->addOption('company-ruc', null, InputOption::VALUE_OPTIONAL, 'Company RUC (11 digits)')
            ->addOption('company-name', null, InputOption::VALUE_OPTIONAL, 'Company business name')
            ->addOption('company-trade', null, InputOption::VALUE_OPTIONAL, 'Company trade name')
            ->addOption('company-address', null, InputOption::VALUE_OPTIONAL, 'Company fiscal address')
            ->addOption('company-phone', null, InputOption::VALUE_OPTIONAL, 'Company phone')
            ->addOption('branch-name', null, InputOption::VALUE_OPTIONAL, 'Branch name')
            ->addOption('branch-address', null, InputOption::VALUE_OPTIONAL, 'Branch address')
            ->addOption('branch-phone', null, InputOption::VALUE_OPTIONAL, 'Branch phone');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenant = trim((string)$input->getArgument('tenant'));
        $domain = trim((string)$input->getOption('domain'));

        if ($tenant === '') {
            $io->error('Tenant name is required.');

            return Command::FAILURE;
        }

        if ($domain === '' && $input->isInteractive()) {
            $domain = (string)$io->ask('Primary domain (leave blank to skip)', $tenant . '.localhost');
            $domain = trim($domain);
        }

        $createUser = (bool)$input->getOption('create-user');
        if (!$createUser && $input->isInteractive()) {
            $createUser = $io->confirm('Create DB user for tenant?', false);
        }

        $dbUserPass = (string)$input->getOption('db-user-pass');
        if ($createUser && $dbUserPass === '' && $input->isInteractive()) {
            $dbUserPass = (string)$io->askHidden('DB user password (hidden)', static function ($value): string {
                if (trim((string)$value) === '') {
                    return 'Admin.3000';
                }

                return (string)$value;
            });
        }

        $seedInitial = (bool)$input->getOption('seed-initial');
        if (!$seedInitial && $input->isInteractive()) {
            $seedInitial = $io->confirm('Seed initial data?', true);
        }

        $seedTest = (bool)$input->getOption('seed-test');
        if (!$seedTest && $input->isInteractive()) {
            $seedTest = $io->confirm('Seed test data?', false);
        }

        $runSetup = (bool)$input->getOption('setup');
        if (!$runSetup && $input->isInteractive()) {
            $runSetup = $io->confirm('Run initial setup (admin/company/branch)?', true);
        }

        if ($runSetup && $seedInitial) {
            $io->note('Initial setup already seeds reference data. Skipping seed-initial.');
            $seedInitial = false;
        }

        $io->title('Provision tenant: ' . $tenant);

        /** @var Connection $controlPlane */
        $controlPlane = $this->connectionRegistry->getConnection('control_plane');
        /** @var Connection $tenantConnection */
        $tenantConnection = $this->connectionRegistry->getConnection('tenant');
        $provisioningConnection = $this->getProvisioningConnection(
            $tenantConnection,
            (string)$input->getOption('provisioning-url')
        );
        $this->ensureControlPlaneReady($controlPlane);

        $io->section('Creating tenant database');
        $provisioningConnection->executeStatement(
            sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                $tenant
            )
        );

        if ($createUser) {
            $io->section('Creating tenant DB user');
            $provisioningConnection->executeStatement(
                sprintf("CREATE USER IF NOT EXISTS '%s'@'%%' IDENTIFIED BY '%s'", $tenant, $dbUserPass)
            );
            $provisioningConnection->executeStatement(
                sprintf("ALTER USER '%s'@'%%' IDENTIFIED BY '%s'", $tenant, $dbUserPass)
            );
            $provisioningConnection->executeStatement(
                sprintf("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%%'", $tenant, $tenant)
            );
            $provisioningConnection->executeStatement('FLUSH PRIVILEGES');
        }

        $io->section('Registering tenant in control plane');
        $controlPlane->executeStatement(
            'INSERT INTO tenants (name, connection, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE connection=VALUES(connection), updated_at=NOW()',
            [$tenant, $tenant, 'active']
        );

        if ($domain !== '') {
            $io->section('Registering domain');
            $tenantId = (int)$controlPlane->fetchOne('SELECT id FROM tenants WHERE name = ?', [$tenant]);
            $controlPlane->executeStatement(
                'INSERT INTO tenant_domains (tenant_id, domain, is_primary) VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE tenant_id=VALUES(tenant_id), is_primary=1',
                [$tenantId, strtolower($domain)]
            );
        }

        $io->section('Running tenant migrations');
        $this->runConsole($io, 'app:tenants:migrate', ['--tenant' => $tenant]);

        if ($seedInitial) {
            $io->section('Seeding initial data');
            $this->runConsole($io, 'app:seed:initial-data', ['--tenant' => $tenant]);
        }

        if ($seedTest) {
            $io->section('Seeding test data');
            $this->runConsole($io, 'app:seed:test-data', ['--tenant' => $tenant]);
        }

        if ($runSetup) {
            $io->section('Running initial setup');
            $this->tenantConnectionSwitcher->switchConnection($tenant);
            $setupInput = $this->buildSetupInput($io, $input);
            try {
                $this->initialSetupService->configure($setupInput);
            } catch (\Throwable $throwable) {
                $io->warning('Initial setup skipped: ' . $throwable->getMessage());
            }
        }

        $io->success('Provisioning completed.');

        return Command::SUCCESS;
    }

    private function ensureControlPlaneReady(Connection $connection): void
    {
        if (!$connection->createSchemaManager()->tablesExist(['tenants'])) {
            throw new \RuntimeException('Control plane migrations missing. Run app:control-plane:migrate first.');
        }
    }

    private function getProvisioningConnection(Connection $fallback, string $overrideUrl): Connection
    {
        $url = $overrideUrl;
        if ($url === '') {
            $url = $_ENV['PROVISIONING_DATABASE_URL']
                ?? $_SERVER['PROVISIONING_DATABASE_URL']
                ?? getenv('PROVISIONING_DATABASE_URL')
                ?: '';
        }
        if (is_string($url) && $url !== '') {
            return DriverManager::getConnection([
                'url' => $url,
                'driver' => 'pdo_mysql',
            ]);
        }

        return $fallback;
    }

    /**
     * @param array<string, string> $options
     */
    private function runConsole(SymfonyStyle $io, string $command, array $options = []): void
    {
        $args = [$command];
        foreach ($options as $key => $value) {
            $args[] = $key . '=' . $value;
        }

        $process = new Process(
            array_merge([PHP_BINARY, 'bin/console'], $args)
        );
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) use ($io) {
            $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Command failed: ' . $command);
        }
    }

    private function buildSetupInput(SymfonyStyle $io, InputInterface $input): InitialSetupInput
    {
        $setup = new InitialSetupInput();

        $setup->adminUsername = (string)($input->getOption('admin-user') ?: $io->ask('Admin username', 'admin'));
        $setup->adminPassword = (string)($input->getOption('admin-pass') ?: $io->askHidden('Admin password (hidden)', static function ($value): string {
            if (trim((string)$value) === '') {
                return 'Admin.3000';
            }

            return (string)$value;
        }));

        $setup->companyRuc = (string)($input->getOption('company-ruc') ?: $io->ask('Company RUC (11 digits)', '20123456789'));
        $setup->companyBusinessName = (string)($input->getOption('company-name') ?: $io->ask('Company business name', 'Empresa Demo'));
        $setup->companyTradeName = (string)($input->getOption('company-trade') ?: $io->ask('Company trade name', 'Demo'));
        $setup->companyFiscalAddress = (string)($input->getOption('company-address') ?: $io->ask('Company fiscal address', 'Av. Principal 123'));
        $setup->companyPhone = (string)($input->getOption('company-phone') ?: $io->ask('Company phone', '999999999'));

        $setup->branchName = (string)($input->getOption('branch-name') ?: $io->ask('Branch name', 'Principal'));
        $setup->branchAddress = (string)($input->getOption('branch-address') ?: $io->ask('Branch address', 'Av. Principal 123'));
        $setup->branchPhone = (string)($input->getOption('branch-phone') ?: $io->ask('Branch phone', '999999999'));

        return $setup;
    }
}
