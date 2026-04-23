<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Command;

use App\ControlPlane\Tenant\Service\TenantConnectionSwitcher;
use App\Dto\Setup\InitialSetupInput;
use App\Setup\InitialSetupService;
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
        private readonly InitialSetupService $initialSetupService,
        private readonly TenantConnectionSwitcher $tenantConnectionSwitcher,
        private readonly CacheInterface $cache,
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
            ->addOption('admin-password', null, InputOption::VALUE_OPTIONAL, 'Admin password for initial setup')
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
                "UPDATE tenant_provisions SET status = 'provisioning', started_at = NOW() WHERE id = ?",
                [(int) $provisionId],
            );
        }

        try {
            $this->doProvision($io, $input, $tenant, $domain);
        } catch (\Throwable $e) {
            if ($provisionId !== null) {
                $controlPlane->executeStatement(
                    "UPDATE tenant_provisions SET status = 'failed', error_message = ?, finished_at = NOW() WHERE id = ?",
                    [$e->getMessage(), (int) $provisionId],
                );
            }
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($provisionId !== null) {
            $controlPlane->executeStatement(
                "UPDATE tenant_provisions SET status = 'completed', finished_at = NOW() WHERE id = ?",
                [(int) $provisionId],
            );
        }

        $io->success(sprintf('Tenant "%s" provisioned successfully.', $tenant));

        return Command::SUCCESS;
    }

    private function doProvision(SymfonyStyle $io, InputInterface $input, string $tenant, string $domain): void
    {
        $provisioningUrl = trim((string) ($input->getOption('provisioning-url') ?: $this->provisioningDatabaseUrl));
        $provisioningConn = DriverManager::getConnection(['url' => $provisioningUrl]);

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

        if ($existing === false) {
            $io->section('Registering tenant in control plane');
            $controlPlane->executeStatement(
                'INSERT INTO tenants (name, primary_domain, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())',
                [$tenant, $domain ?: null, 'active'],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
            );
            $io->writeln(sprintf('  Tenant <info>%s</info> registered.', $tenant));
        } else {
            $io->writeln(sprintf('  Tenant <info>%s</info> already registered, skipping.', $tenant));
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
                $this->initialSetupService->configure($setup);
                $io->writeln('  Initial setup completed.');
            } catch (BadRequestHttpException $e) {
                $io->warning('Initial setup skipped: ' . $e->getMessage());
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

    private function buildSetupInput(InputInterface $input): InitialSetupInput
    {
        $setup = new InitialSetupInput();
        $setup->adminUsername = (string) ($input->getOption('admin-user') ?: 'admin');
        $setup->adminPassword = (string) ($input->getOption('admin-password') ?: 'Admin.3000');
        $setup->companyRuc = (string) ($input->getOption('company-ruc') ?: '20123456789');
        $setup->companyBusinessName = (string) ($input->getOption('company-name') ?: 'Empresa Demo');
        $setup->companyTradeName = (string) ($input->getOption('company-trade') ?: 'Demo');
        $setup->companyFiscalAddress = (string) ($input->getOption('company-address') ?: 'Av. Principal 123');
        $setup->companyPhone = (string) ($input->getOption('company-phone') ?: '999999999');
        $setup->branchName = (string) ($input->getOption('branch-name') ?: 'Principal');
        $setup->branchAddress = (string) ($input->getOption('branch-address') ?: 'Av. Principal 123');
        $setup->branchPhone = (string) ($input->getOption('branch-phone') ?: '999999999');

        return $setup;
    }
}
