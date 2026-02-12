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
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(
    name: 'app:tenant:provision',
    description: 'Provision a tenant (DB, control plane, migrations, optional seed).',
)]
class TenantProvisionCommand extends Command
{
    public function __construct(
        private readonly ConnectionRegistry $connectionRegistry,
        private readonly InitialSetupService $initialSetupService,
        private readonly TenantConnectionSwitcher $tenantConnectionSwitcher,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'Tenant name (slug)')
            ->addOption('domain', null, InputOption::VALUE_OPTIONAL, 'Primary domain for tenant')
            ->addOption('create-user', null, InputOption::VALUE_NONE, 'Create DB user equal to tenant name')
            ->addOption('provisioning-url', null, InputOption::VALUE_OPTIONAL, 'Provisioning database URL')
            ->addOption('seed-initial', null, InputOption::VALUE_NONE, 'Run initial data seed')
            ->addOption('seed-test', null, InputOption::VALUE_NONE, 'Run test data seed')
            ->addOption('setup', null, InputOption::VALUE_NONE, 'Run initial setup (admin, company, branch)')
            ->addOption('admin-user', null, InputOption::VALUE_OPTIONAL, 'Admin username')
            ->addOption('company-ruc', null, InputOption::VALUE_OPTIONAL, 'Company RUC (11 digits)')
            ->addOption('company-name', null, InputOption::VALUE_OPTIONAL, 'Company business name')
            ->addOption('company-trade', null, InputOption::VALUE_OPTIONAL, 'Company trade name')
            ->addOption('company-address', null, InputOption::VALUE_OPTIONAL, 'Company fiscal address')
            ->addOption('company-phone', null, InputOption::VALUE_OPTIONAL, 'Company phone')
            ->addOption('branch-name', null, InputOption::VALUE_OPTIONAL, 'Branch name')
            ->addOption('branch-address', null, InputOption::VALUE_OPTIONAL, 'Branch address')
            ->addOption('branch-phone', null, InputOption::VALUE_OPTIONAL, 'Branch phone')
            ->addOption('provision-id', null, InputOption::VALUE_OPTIONAL, 'Provision tracking ID from control plane');
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

        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,61}[a-z0-9]$/i', $tenant)) {
            $io->error(
                'Tenant name must be 3-63 characters, alphanumeric with hyphens/underscores'
            );

            return Command::FAILURE;
        }

        $tenant = strtolower($tenant);

        if ($domain === '' && $input->isInteractive()) {
            $domain = (string)$io->ask('Primary domain (leave blank to skip)', $tenant . '.localhost');
            $domain = trim($domain);
        }

        $createUser = (bool)$input->getOption('create-user');
        if (!$createUser && $input->isInteractive()) {
            $createUser = $io->confirm('Create DB user for tenant?', false);
        }

        $dbUserPass = $this->resolveSecret('TENANT_DB_PASS');
        if ($createUser && $dbUserPass === '') {
            if ($input->isInteractive()) {
                $dbUserPass = (string)$io->askHidden('DB user password (hidden)');
            }
            if ($dbUserPass === '') {
                $io->error('DB user password is required. Set TENANT_DB_PASS env var or provide interactively.');

                return Command::FAILURE;
            }
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

        $provisionId = (int)$input->getOption('provision-id');

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

        if ($provisionId > 0) {
            $this->updateProvisionStatus($controlPlane, $provisionId, 'running');
        }

        $dbCreated = false;
        $userCreated = false;
        $controlPlaneRegistered = false;

        try {
            $io->section('Creating tenant database');
            $quotedDb = $provisioningConnection->quoteSingleIdentifier($tenant);
            $provisioningConnection->executeStatement(
                'CREATE DATABASE IF NOT EXISTS ' . $quotedDb . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
            $dbCreated = true;

            if ($createUser) {
                $io->section('Creating tenant DB user');
                $quotedUser = $provisioningConnection->quote($tenant);
                $quotedPass = $provisioningConnection->quote($dbUserPass);
                $provisioningConnection->executeStatement(
                    'CREATE USER IF NOT EXISTS ' . $quotedUser . '@\'%\' IDENTIFIED BY ' . $quotedPass
                );
                $provisioningConnection->executeStatement(
                    'ALTER USER ' . $quotedUser . '@\'%\' IDENTIFIED BY ' . $quotedPass
                );
                $provisioningConnection->executeStatement(
                    'GRANT ALL PRIVILEGES ON ' . $quotedDb . '.* TO ' . $quotedUser . '@\'%\''
                );
                $provisioningConnection->executeStatement('FLUSH PRIVILEGES');
                $userCreated = true;
            }

            $io->section('Registering tenant in control plane');
            $controlPlane->executeStatement(
                'INSERT INTO tenants (name, connection, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE connection=VALUES(connection), updated_at=NOW()',
                [$tenant, $tenant, 'active']
            );
            $this->invalidateTenantCache($tenant);
            $controlPlaneRegistered = true;

            if ($domain !== '') {
                $io->section('Registering domain');
                $tenantId = (int)$controlPlane->fetchOne('SELECT id FROM tenants WHERE name = ?', [$tenant]);
                $controlPlane->executeStatement(
                    'INSERT INTO tenant_domains (tenant_id, domain, is_primary) VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE tenant_id=VALUES(tenant_id), is_primary=1',
                    [$tenantId, strtolower($domain)]
                );
                $this->invalidateTenantDomainCache($domain);
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
                try {
                    $setupInput = $this->buildSetupInput($io, $input);
                    $this->initialSetupService->configure($setupInput);
                } catch (BadRequestHttpException) {
                    $io->note('Setup already completed, skipping.');
                }
            }
        } catch (\Throwable $exception) {
            $io->error('Provisioning failed: ' . $exception->getMessage());

            if ($provisionId > 0) {
                $this->updateProvisionStatus(
                    $controlPlane,
                    $provisionId,
                    'failed',
                    mb_substr($exception::class . ': ' . $exception->getMessage(), 0, 500)
                );
            }

            $io->section('Rolling back provisioning');

            $this->rollback(
                $io,
                $provisioningConnection,
                $controlPlane,
                $tenant,
                $dbCreated,
                $userCreated,
                $controlPlaneRegistered
            );

            return Command::FAILURE;
        }

        if ($provisionId > 0) {
            $this->updateProvisionStatus($controlPlane, $provisionId, 'completed');
        }

        $io->success('Provisioning completed.');

        return Command::SUCCESS;
    }

    private function updateProvisionStatus(
        Connection $controlPlane,
        int $provisionId,
        string $status,
        string $errorMessage = '',
    ): void {
        try {
            $sets = ['status = ?'];
            $params = [$status];
            $types = [];

            if ($status === 'running') {
                $sets[] = 'started_at = NOW()';
            } elseif ($status === 'completed' || $status === 'failed') {
                $sets[] = 'completed_at = NOW()';
            }

            if ($errorMessage !== '') {
                $sets[] = 'error_message = ?';
                $params[] = $errorMessage;
            }

            $params[] = $provisionId;
            $types[] = ParameterType::INTEGER;

            $controlPlane->executeStatement(
                'UPDATE tenant_provisions SET ' . implode(', ', $sets) . ' WHERE id = ?',
                $params,
                [...array_fill(0, count($params) - 1, ParameterType::STRING), ParameterType::INTEGER]
            );
        } catch (\Throwable) {
            // Don't let tracking failures break provisioning
        }
    }

    private function rollback(
        SymfonyStyle $io,
        Connection $provisioningConnection,
        Connection $controlPlane,
        string $tenant,
        bool $dbCreated,
        bool $userCreated,
        bool $controlPlaneRegistered,
    ): void {
        if ($controlPlaneRegistered) {
            try {
                $controlPlane->executeStatement('DELETE FROM tenants WHERE name = ?', [$tenant]);
                $io->note('Rolled back: control plane registration');
            } catch (\Throwable $e) {
                $io->warning('Rollback failed (control plane): ' . $e->getMessage());
            }
        }

        if ($userCreated) {
            try {
                $quotedUser = $provisioningConnection->quote($tenant);
                $provisioningConnection->executeStatement('DROP USER IF EXISTS ' . $quotedUser . '@\'%\'');
                $io->note('Rolled back: DB user');
            } catch (\Throwable $e) {
                $io->warning('Rollback failed (DB user): ' . $e->getMessage());
            }
        }

        if ($dbCreated) {
            try {
                $quotedDb = $provisioningConnection->quoteSingleIdentifier($tenant);
                $provisioningConnection->executeStatement('DROP DATABASE IF EXISTS ' . $quotedDb);
                $io->note('Rolled back: database');
            } catch (\Throwable $e) {
                $io->warning('Rollback failed (database): ' . $e->getMessage());
            }
        }

        $this->invalidateTenantCache($tenant);
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
            $params = $this->buildProvisioningParams($url);

            return DriverManager::getConnection($params);
        }

        return $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProvisioningParams(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false) {
            throw new \RuntimeException('Invalid PROVISIONING_DATABASE_URL.');
        }

        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $params = [
            'driver' => 'pdo_mysql',
            'host' => $parts['host'] ?? null,
            'port' => isset($parts['port']) ? (int)$parts['port'] : 3306,
            'user' => $parts['user'] ?? null,
            'password' => $parts['pass'] ?? null,
        ];

        if (!empty($parts['path']) && $parts['path'] !== '/') {
            $params['dbname'] = ltrim($parts['path'], '/');
        }

        if (isset($query['serverVersion'])) {
            $params['serverVersion'] = $query['serverVersion'];
        }
        if (isset($query['charset'])) {
            $params['charset'] = $query['charset'];
        }

        if (empty($params['host'])) {
            throw new \RuntimeException('Provisioning DB host is required in PROVISIONING_DATABASE_URL.');
        }

        return $params;
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
        $process->run(function ($type, $buffer) use ($io): void {
            unset($type);
            $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Command failed: ' . $command);
        }
    }

    private function resolveSecret(string $envVar): string
    {
        return trim((string)($_ENV[$envVar] ?? $_SERVER[$envVar] ?? getenv($envVar) ?: ''));
    }

    private function invalidateTenantCache(string $tenantName): void
    {
        $tenantName = trim($tenantName);
        if ($tenantName === '') {
            return;
        }

        $this->cache->delete('tenant_name_' . hash('xxh3', $tenantName));
    }

    private function invalidateTenantDomainCache(string $domain): void
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return;
        }

        $this->cache->delete('tenant_domain_' . hash('xxh3', $domain));
    }

    private function buildSetupInput(SymfonyStyle $io, InputInterface $input): InitialSetupInput
    {
        $setup = new InitialSetupInput();

        $setup->adminUsername = (string)($input->getOption('admin-user')
            ?: $io->ask('Admin username', 'admin'));

        $adminPass = $this->resolveSecret('TENANT_ADMIN_PASS');
        if ($adminPass === '' && $input->isInteractive()) {
            $adminPass = (string)$io->askHidden('Admin password (hidden)');
        }
        if ($adminPass === '') {
            throw new \RuntimeException(
                'Admin password is required. Set TENANT_ADMIN_PASS env var or provide interactively.'
            );
        }
        $setup->adminPassword = $adminPass;

        $setup->companyRuc = (string)($input->getOption('company-ruc')
            ?: $io->ask('Company RUC (11 digits)', '20000000001'));
        $setup->companyBusinessName = (string)($input->getOption('company-name')
            ?: $io->ask('Company business name', 'Demo SAC'));
        $setup->companyTradeName = (string)($input->getOption('company-trade')
            ?: $io->ask('Company trade name', 'Demo'));
        $setup->companyFiscalAddress = (string)($input->getOption('company-address')
            ?: $io->ask('Company fiscal address', 'Av. Principal 123'));
        $setup->companyPhone = (string)($input->getOption('company-phone')
            ?: $io->ask('Company phone', '999999999'));

        $setup->branchName = (string)($input->getOption('branch-name')
            ?: $io->ask('Branch name', 'Principal'));
        $setup->branchAddress = (string)($input->getOption('branch-address')
            ?: $io->ask('Branch address', 'Av. Principal 123'));
        $setup->branchPhone = (string)($input->getOption('branch-phone')
            ?: $io->ask('Branch phone', '999999999'));

        return $setup;
    }
}
