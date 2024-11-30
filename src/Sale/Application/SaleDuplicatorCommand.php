<?php

namespace App\Sale\Application;

use App\Core\Infrastructure\Service\TenantConnectionSwitcher;
use App\Core\Infrastructure\Service\TenantManager;
use App\Sale\Domain\CreateSaleUseCase;
use App\Sale\Domain\GetOneSaleUseCase;
use App\Sale\Domain\Sale;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sale:duplicate',
    description: 'Add a short description for your command',
)]
class SaleDuplicatorCommand extends Command
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly TenantConnectionSwitcher $tenantConnectionSwitcher,
        private readonly Connection $connection,
        private readonly GetOneSaleUseCase $getOneSaleUseCase,
        private readonly CreateSaleUseCase $createSaleUseCase,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command allows you to duplicate sales');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $tenants = $this->tenantManager->getTenants();

            /** @var string $selectedTenant */
            $selectedTenant = $io->choice('Select tenant', array_map(fn ($tenant) => $tenant['name'], $tenants));
            $io->writeln('Tenant selected: '.$selectedTenant);
            $io->writeln('Switching tenant connection...');
            $this->tenantConnectionSwitcher->switchConnection($selectedTenant);
            $io->writeln('Tenant connection switched');
            /** @var string $sales */
            $sales = $io->ask('Enter sale id or ids to duplicate (separated by comma)');
            $saleList = explode(',', $sales);
            $io->writeln('Duplicating sales...');
            $saleFields = get_object_vars(new Sale());
            $customFields = [];
            while ($io->confirm('Do you want to customize the sales?')) {
                $field = $io->choice('Select field', array_keys($saleFields));
                /** @var string $value */
                $value = $io->ask('Enter value');
                if ('fecha' === $field) {
                    $value = new \DateTime($value);
                }
                $customFields[$field] = $value;
            }
            foreach ($saleList as $saleId) {
                $sale = $this->getOneSaleUseCase->__invoke($saleId);
                $sale->movimiento_id = null;
                $sale->date_created = new \DateTime();
                $sale->serie = null;
                $sale->numero = null;
                $sale->has_invoice = false;
                $sale->invoice = null;
                $sale->estado = 1;

                foreach ($sale->details as $detail) {
                    $detail->id = null;
                    $detail->movimiento = null;
                }

                foreach ($customFields as $field => $value) {
                    $sale->$field = $value;
                }

                $sale = $this->createSaleUseCase->__invoke($sale);

                $io->writeln('Sale duplicated: '.$sale->movimiento_id);
            }

            $io->success('Sales duplicated successfully');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
