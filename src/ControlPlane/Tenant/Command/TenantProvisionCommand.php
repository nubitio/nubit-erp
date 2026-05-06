<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use App\ControlPlane\Tenant\Port\ElectronicBillingCompanyPortInterface;
use App\ControlPlane\Tenant\Port\InitialTenantSetupPortInterface;
use App\ControlPlane\Tenant\Port\InitialTenantSetupRequest;
use App\ControlPlane\Tenant\Service\TenantConnectionSwitcher;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        private readonly TenantConnectionSwitcher $tenantConnectionSwitcher,
        private readonly CacheInterface $cache,
        private readonly InitialTenantSetupPortInterface $initialTenantSetup,
        private readonly ElectronicBillingCompanyPortInterface $electronicBillingCompany,
        #[Autowire(env: 'TENANT_DB_PASS')]
        private readonly string $tenantDbPass,
        #[Autowire(env: 'PROVISIONING_DATABASE_URL')]
        private readonly string $provisioningDatabaseUrl,
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
            ->addOption('admin-password', null, InputOption::VALUE_OPTIONAL, 'Admin password for initial setup (prefer TENANT_ADMIN_PASSWORD env)')
            ->addOption('company-ruc', null, InputOption::VALUE_OPTIONAL, 'Company RUC (11 digits)')
            ->addOption('company-name', null, InputOption::VALUE_OPTIONAL, 'Company business name')
            ->addOption('company-trade', null, InputOption::VALUE_OPTIONAL, 'Company trade name')
            ->addOption('company-address', null, InputOption::VALUE_OPTIONAL, 'Company fiscal address')
            ->addOption('company-phone', null, InputOption::VALUE_OPTIONAL, 'Company phone')
            ->addOption('branch-name', null, InputOption::VALUE_OPTIONAL, 'Branch name')
            ->addOption('branch-address', null, InputOption::VALUE_OPTIONAL, 'Branch address')
            ->addOption('branch-phone', null, InputOption::VALUE_OPTIONAL, 'Branch phone')
            ->addOption('provision-id', null, InputOption::VALUE_OPTIONAL, 'Provision tracking ID from control plane')
            ->addOption('lycet-upsert', null, InputOption::VALUE_NONE, 'Register/update company in lycet after provisioning')
            ->addOption('lycet-sol-user', null, InputOption::VALUE_OPTIONAL, 'SOL user for lycet (RUC+user, e.g. 20000000001MODDATOS)')
            ->addOption('lycet-sol-pass', null, InputOption::VALUE_OPTIONAL, 'SOL password for lycet')
            ->addOption('lycet-cert-file', null, InputOption::VALUE_OPTIONAL, 'Path to .pem certificate file for lycet')
            ->addOption('lycet-logo-file', null, InputOption::VALUE_OPTIONAL, 'Path to logo file for lycet (optional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenant = trim((string) $input->getArgument('tenant'));
        $domain = trim((string) $input->getOption('domain'));

        if ($tenant === '') {
            $io->error('Tenant name is required.');

            return Command::FAILURE;
        }

        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,61}[a-z0-9]$/i', $tenant)) {
            $io->error('Invalid tenant name. Use 3-63 alphanumeric chars, underscores or hyphens.');

            return Command::FAILURE;
        }

        $provisionId = $input->getOption('provision-id');
        $controlPlane = $this->connectionRegistry->getConnection('control_plane');

        if ($provisionId !== null) {
            $controlPlane->executeStatement(
                "UPDATE tenant_provisions SET status = 'running', started_at = NOW() WHERE id = ?",
                [(int) $provisionId],
            );
        }

        try {
            $this->doProvision($io, $input, $tenant, $domain);
        } catch (\Throwable $e) {
            if ($provisionId !== null) {
                $controlPlane->executeStatement(
                    "UPDATE tenant_provisions SET status = 'failed', error_message = ?, completed_at = NOW() WHERE id = ?",
                    [$e->getMessage(), (int) $provisionId],
                );
            }
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($provisionId !== null) {
            $controlPlane->executeStatement(
                "UPDATE tenant_provisions SET status = 'completed', completed_at = NOW() WHERE id = ?",
                [(int) $provisionId],
            );
        }

        $io->success(sprintf('Tenant "%s" provisioned successfully.', $tenant));

        return Command::SUCCESS;
    }

    private function doProvision(SymfonyStyle $io, InputInterface $input, string $tenant, string $domain): void
    {
        $provisioningUrl = trim((string) ($input->getOption('provisioning-url') ?: $this->provisioningDatabaseUrl));
        $provisioningConn = DriverManager::getConnection($this->parseDbUrl($provisioningUrl));

        $io->section('Creating database');
        $provisioningConn->executeStatement(
            sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $tenant),
        );
        $io->writeln(sprintf('  Database <info>%s</info> created.', $tenant));

        if ($input->getOption('create-user')) {
            $io->section('Creating DB user');
            $provisioningConn->executeStatement(
                sprintf("CREATE USER IF NOT EXISTS '%s'@'%%' IDENTIFIED BY ?", $tenant),
                [$this->tenantDbPass],
            );
            $provisioningConn->executeStatement(
                sprintf("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%%'", $tenant, $tenant),
            );
            $provisioningConn->executeStatement('FLUSH PRIVILEGES');
            $io->writeln(sprintf('  User <info>%s</info> created.', $tenant));
        }

        $controlPlane = $this->connectionRegistry->getConnection('control_plane');
        $existing = $controlPlane->fetchOne('SELECT id FROM tenants WHERE name = ?', [$tenant]);
        $tenantId = $existing === false ? null : (int) $existing;

        if ($existing === false) {
            $io->section('Registering tenant in control plane');
            $controlPlane->executeStatement(
                'INSERT INTO tenants (name, connection, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())',
                [$tenant, $tenant, 'active'],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
            );
            $tenantId = (int) $controlPlane->lastInsertId();
            $io->writeln(sprintf('  Tenant <info>%s</info> registered.', $tenant));
        } else {
            $controlPlane->executeStatement(
                'UPDATE tenants SET connection = ?, status = ?, updated_at = NOW() WHERE id = ?',
                [$tenant, 'active', $tenantId],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
            );
            $io->writeln(sprintf('  Tenant <info>%s</info> already registered, skipping.', $tenant));
        }

        if ($domain !== '' && $tenantId !== null) {
            $this->upsertPrimaryDomain($controlPlane, $tenantId, $domain);
        }

        $this->cache->delete('tenants_list');

        $io->section('Running migrations');
        $this->tenantConnectionSwitcher->switchConnection($tenant);
        $migrate = new Process([PHP_BINARY, 'bin/console', 'app:tenants:migrate', '--tenant=' . $tenant]);
        $migrate->setTimeout(300);
        $migrate->run($this->processOutputCallback($io));

        if (!$migrate->isSuccessful()) {
            throw new \RuntimeException('Migration failed: ' . $migrate->getErrorOutput());
        }

        if ($input->getOption('seed-initial')) {
            $io->section('Running initial seed');
            $seed = new Process([PHP_BINARY, 'bin/console', 'app:seed:initial', '--tenant=' . $tenant]);
            $seed->setTimeout(120);
            $seed->run($this->processOutputCallback($io));
        }

        if ($input->getOption('seed-test')) {
            $io->section('Running test seed');
            $seed = new Process([PHP_BINARY, 'bin/console', 'app:seed:test', '--tenant=' . $tenant]);
            $seed->setTimeout(120);
            $seed->run($this->processOutputCallback($io));
        }

        if ($input->getOption('setup')) {
            $io->section('Running initial setup');
            $setup = $this->buildSetupInput($input);

            try {
                $this->initialTenantSetup->configure($setup);
                $io->writeln('  Initial setup completed.');
            } catch (BadRequestHttpException $e) {
                $io->warning('Initial setup skipped: ' . $e->getMessage());
            }
        }

        if ($input->getOption('lycet-upsert')) {
            $io->section('Registering company in lycet');
            $ruc      = (string) ($input->getOption('company-ruc') ?: '');
            $solUser  = (string) ($input->getOption('lycet-sol-user') ?: '');
            $solPass  = (string) ($input->getOption('lycet-sol-pass') ?: '');
            $certFile = (string) ($input->getOption('lycet-cert-file') ?: '');

            if ($ruc === '' || $solUser === '' || $solPass === '' || $certFile === '') {
                $io->warning('lycet-upsert skipped: --company-ruc, --lycet-sol-user, --lycet-sol-pass and --lycet-cert-file are all required.');
            } elseif (!file_exists($certFile)) {
                $io->warning(sprintf('lycet-upsert skipped: certificate file not found: %s', $certFile));
            } else {
                $certPem = (string) file_get_contents($certFile);

                try {
                    $this->electronicBillingCompany->upsertCompany($ruc, $solUser, $solPass, $certPem);
                    $io->writeln(sprintf('  Company <info>%s</info> registered in lycet.', $ruc));
                } catch (\Throwable $e) {
                    $io->warning('lycet-upsert failed: ' . $e->getMessage());
                }

                $logoFile = (string) ($input->getOption('lycet-logo-file') ?: '');
                if ($logoFile !== '' && file_exists($logoFile)) {
                    try {
                        $this->electronicBillingCompany->uploadCompanyLogo($ruc, (string) file_get_contents($logoFile));
                        $io->writeln('  Logo uploaded to lycet.');
                    } catch (\Throwable $e) {
                        $io->warning('lycet logo upload failed: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Returns a Process output callback that writes to the console.
     * The $type parameter (stdout/stderr discriminator) is intentionally unused.
     */
    private function processOutputCallback(SymfonyStyle $io): \Closure
    {
        return static function (string $type, string $buffer) use ($io): void {
            $io->write($buffer);
        };
    }

    private function upsertPrimaryDomain(Connection $controlPlane, int $tenantId, string $domain): void
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return;
        }

        $existingDomain = $controlPlane->fetchAssociative('SELECT id, tenant_id FROM tenant_domains WHERE domain = ?', [$domain]);
        if ($existingDomain !== false && (int) $existingDomain['tenant_id'] !== $tenantId) {
            throw new \RuntimeException(sprintf('Domain "%s" is already registered to another tenant.', $domain));
        }

        $controlPlane->executeStatement(
            'UPDATE tenant_domains SET is_primary = 0 WHERE tenant_id = ?',
            [$tenantId],
            [ParameterType::INTEGER],
        );

        if ($existingDomain === false) {
            $controlPlane->executeStatement(
                'INSERT INTO tenant_domains (tenant_id, domain, is_primary) VALUES (?, ?, 1)',
                [$tenantId, $domain],
                [ParameterType::INTEGER, ParameterType::STRING],
            );

            return;
        }

        $controlPlane->executeStatement(
            'UPDATE tenant_domains SET is_primary = 1 WHERE id = ?',
            [(int) $existingDomain['id']],
            [ParameterType::INTEGER],
        );
    }

    private function buildSetupInput(InputInterface $input): InitialTenantSetupRequest
    {
        return new InitialTenantSetupRequest(
            (string) ($input->getOption('admin-user') ?: 'admin'),
            $this->resolveAdminPassword($input),
            (string) ($input->getOption('company-ruc') ?: '20123456789'),
            (string) ($input->getOption('company-name') ?: 'Empresa Demo'),
            (string) ($input->getOption('company-trade') ?: 'Demo'),
            (string) ($input->getOption('company-address') ?: 'Av. Principal 123'),
            (string) ($input->getOption('company-phone') ?: '999999999'),
            (string) ($input->getOption('branch-name') ?: 'Principal'),
            (string) ($input->getOption('branch-address') ?: 'Av. Principal 123'),
            (string) ($input->getOption('branch-phone') ?: '999999999'),
        );
    }

    /**
     * Parse a database URL into DBAL connection params.
     *
     * DBAL 4 no longer infers the driver from the URL scheme when only 'url' is
     * passed — it requires an explicit 'driver' key. Rather than mixing 'url'
     * with 'driver' (which produces an empty DSN), we parse the URL ourselves
     * and return a plain params array that DBAL 4 accepts without ambiguity.
     *
     * @return array<string, mixed>
     */
    private function parseDbUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            throw new \InvalidArgumentException(sprintf('Invalid provisioning database URL: "%s"', $url));
        }

        $params = [
            'driver'  => 'pdo_mysql',
            'host'    => $parts['host'],
            'port'    => $parts['port'] ?? 3306,
            'user'    => isset($parts['user']) ? urldecode($parts['user']) : '',
            'password' => isset($parts['pass']) ? urldecode($parts['pass']) : '',
            'charset' => 'utf8mb4',
        ];

        // Do NOT set dbname: the provisioning connection must connect without
        // selecting a database so it can run CREATE DATABASE for new tenants.

        // Forward serverVersion from query string if present (informational only)
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (!empty($query['serverVersion'])) {
                $params['serverVersion'] = $query['serverVersion'];
            }
        }

        return $params;
    }

    private function resolveAdminPassword(InputInterface $input): string
    {
        $fromEnv = trim((string) ($_SERVER['TENANT_ADMIN_PASSWORD'] ?? $_ENV['TENANT_ADMIN_PASSWORD'] ?? getenv('TENANT_ADMIN_PASSWORD') ?: ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return (string) ($input->getOption('admin-password') ?: 'Admin.3000');
    }
}
