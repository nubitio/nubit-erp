<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use App\Dto\Setup\InitialSetupInput;
use App\Setup\InitialSetupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tenant:setup',
    description: 'Run initial setup (admin/company/branch) for a tenant.',
)]
class TenantSetupCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly InitialSetupService $initialSetupService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
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

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $setup = $this->buildSetupInput($io, $input);

        try {
            $this->initialSetupService->configure($setup);
            $io->success('Initial setup completed.');
        } catch (\Throwable $throwable) {
            $io->warning('Initial setup skipped: ' . $throwable->getMessage());
        }

        return Command::SUCCESS;
    }

    private function buildSetupInput(SymfonyStyle $io, InputInterface $input): InitialSetupInput
    {
        $setup = new InitialSetupInput();

        $setup->adminUsername = (string)($input->getOption('admin-user') ?: ($input->isInteractive()
            ? $io->ask('Admin username', 'admin')
            : 'admin'));

        $setup->adminPassword = (string)($input->getOption('admin-pass') ?: ($input->isInteractive()
            ? $io->askHidden('Admin password (hidden)', static function ($value): string {
                if (trim((string)$value) === '') {
                    return 'Admin.3000';
                }

                return (string)$value;
            })
            : 'Admin.3000'));

        $setup->companyRuc = (string)($input->getOption('company-ruc') ?: ($input->isInteractive()
            ? $io->ask('Company RUC (11 digits)', '20123456789')
            : '20123456789'));

        $setup->companyBusinessName = (string)($input->getOption('company-name') ?: ($input->isInteractive()
            ? $io->ask('Company business name', 'Empresa Demo')
            : 'Empresa Demo'));

        $setup->companyTradeName = (string)($input->getOption('company-trade') ?: ($input->isInteractive()
            ? $io->ask('Company trade name', 'Demo')
            : 'Demo'));

        $setup->companyFiscalAddress = (string)($input->getOption('company-address') ?: ($input->isInteractive()
            ? $io->ask('Company fiscal address', 'Av. Principal 123')
            : 'Av. Principal 123'));

        $setup->companyPhone = (string)($input->getOption('company-phone') ?: ($input->isInteractive()
            ? $io->ask('Company phone', '999999999')
            : '999999999'));

        $setup->branchName = (string)($input->getOption('branch-name') ?: ($input->isInteractive()
            ? $io->ask('Branch name', 'Principal')
            : 'Principal'));

        $setup->branchAddress = (string)($input->getOption('branch-address') ?: ($input->isInteractive()
            ? $io->ask('Branch address', 'Av. Principal 123')
            : 'Av. Principal 123'));

        $setup->branchPhone = (string)($input->getOption('branch-phone') ?: ($input->isInteractive()
            ? $io->ask('Branch phone', '999999999')
            : '999999999'));

        return $setup;
    }
}
