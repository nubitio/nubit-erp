<?php

declare(strict_types=1);

namespace App\Sales\Command;

use App\Accounting\Entity\Account;
use App\Accounting\Repository\AccountRepository;
use App\Accounting\Service\DefaultPostingService;
use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Organization\Entity\Branch;
use App\Treasury\Repository\GatewayRouteRepository;
use App\Treasury\Repository\TreasuryAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sales:gateway-route:set',
    description: 'Set the Treasury and ledger accounts for a gateway payment method.',
)]
final class ConfigureGatewayRouteCommand extends PerTenantCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GatewayRouteRepository $routes,
        private readonly TreasuryAccountRepository $treasury,
        private readonly AccountRepository $ledger,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument('branch', InputArgument::REQUIRED)
            ->addArgument('method', InputArgument::REQUIRED)
            ->addArgument('account', InputArgument::REQUIRED)
            ->addArgument('ledger-code', InputArgument::REQUIRED);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (trim((string) $input->getOption('tenant')) === '') {
            new SymfonyStyle($input, $output)->error('Indica el tenant con --tenant.');

            return Command::INVALID;
        }

        return parent::execute($input, $output);
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $branchId = (string) $input->getArgument('branch');
        $methodId = filter_var($input->getArgument('method'), FILTER_VALIDATE_INT);
        $accountId = (string) $input->getArgument('account');
        $code = (string) $input->getArgument('ledger-code');
        $branch = $this->entityManager->find(Branch::class, $branchId);
        $method = $methodId === false ? null : $this->entityManager->find(PaymentMethod::class, $methodId);
        $account = $this->treasury->gatewayAccount($accountId, $branchId);
        $ledgerAccount = $this->ledger->findPostableByCode($code);
        if (
            $branch?->getIsActive() !== true
            || $methodId === PaymentMethod::CASH
            || $method?->getIsActive() !== true
            || $method->getSettlementBehavior() !== SettlementBehavior::COLLECTED
            || $account?->currency !== 'PEN'
            || $ledgerAccount === null
            || $ledgerAccount->getType() !== Account::ASSET
            || in_array(
                $code,
                [DefaultPostingService::CASH, DefaultPostingService::BANK, DefaultPostingService::RECEIVABLE],
                strict: true,
            )
        ) {
            new SymfonyStyle($input, $output)->error(
                'La sucursal, el medio, la cuenta PEN y la cuenta contable deben ser válidos para fondos por liquidar.',
            );

            return Command::INVALID;
        }

        $this->routes->save($branchId, (int) $methodId, 'PEN', $accountId, $ledgerAccount->getCode());

        return Command::SUCCESS;
    }
}
