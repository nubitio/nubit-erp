<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\TestDataSeeder;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:test-data',
    description: 'Seed test data for development (customers, products, categories, units, sales)',
)]
class SeedTestDataCommand extends Command
{
    public function __construct(
        private readonly TestDataSeeder $testDataSeeder,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('customers', 'c', InputOption::VALUE_OPTIONAL, 'Number of customers to create', 300)
            ->addOption('products', 'p', InputOption::VALUE_OPTIONAL, 'Number of products to create', 500)
            ->addOption('suppliers', 's', InputOption::VALUE_OPTIONAL, 'Number of suppliers to create', 20)
            ->addOption('sales', null, InputOption::VALUE_OPTIONAL, 'Number of sales to create', 200)
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Batch size for flushing', 50)
            ->addOption('purge', null, InputOption::VALUE_NONE, 'Purge existing test data before seeding')
            ->setHelp(
                <<<'HELP'
This command seeds test data for development purposes.

It creates:
- Categories with parent-child relationships
- Additional units of measure
- Suppliers with Peruvian RUC format
- Customers (companies and individuals)
- Products with categories, units, prices and stock
- Sales with multiple items

Example usage:
  php bin/console app:seed:test-data
  php bin/console app:seed:test-data --customers=500 --products=1000
  php bin/console app:seed:test-data -c 100 -p 200 -s 10 --sales=50
  php bin/console app:seed:test-data --purge  # Clear existing data first

Note: This command is idempotent - it will skip existing records based on unique identifiers.

The --purge option will truncate the following tables:
  - Sales and sale items
  - Products and product stock
  - Customers
  - Suppliers
  - Categories (except default "General")
  - Units of measure (except default "NIU")

⚠️  WARNING: --purge will DELETE ALL TEST DATA. Use with caution!
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $customerCount = (int)$input->getOption('customers');
        $productCount = (int)$input->getOption('products');
        $supplierCount = (int)$input->getOption('suppliers');
        $saleCount = (int)$input->getOption('sales');
        $batchSize = (int)$input->getOption('batch-size');
        $purge = (bool)$input->getOption('purge');

        $io->title('Seeding Test Data');

        if ($purge) {
            $io->warning('PURGE MODE: All existing test data will be deleted!');

            if (!$io->confirm('Are you sure you want to continue?', false)) {
                $io->note('Operation cancelled.');

                return Command::SUCCESS;
            }

            try {
                $io->section('Purging existing test data...');
                $this->purgeTestData($io);
                $io->success('Test data purged successfully!');
            } catch (\Throwable $e) {
                $io->error('Failed to purge test data: ' . $e->getMessage());

                return Command::FAILURE;
            }
        }

        $io->info(
            sprintf(
                'Creating: %d customers, %d products, %d suppliers, %d sales',
                $customerCount,
                $productCount,
                $supplierCount,
                $saleCount
            )
        );

        try {
            $io->section('Starting seed process...');

            $stats = $this->testDataSeeder->seed(
                customerCount: $customerCount,
                productCount: $productCount,
                supplierCount: $supplierCount,
                saleCount: $saleCount,
                batchSize: $batchSize
            );

            $io->success('Test data seeded successfully!');

            $io->table(
                ['Entity', 'Records Created'],
                [
                    ['Categories', $stats['categories_created']],
                    ['Units of Measure', $stats['units_created']],
                    ['Suppliers', $stats['suppliers_created']],
                    ['Customers', $stats['customers_created']],
                    ['Products', $stats['products_created']],
                    ['Sales', $stats['sales_created']],
                    ['Invoices', $stats['invoices_created']],
                ]
            );

            return Command::SUCCESS;
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            $io->note('Make sure you have run the initial setup first: POST /api/setup');

            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error('An error occurred while seeding test data: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function purgeTestData(SymfonyStyle $io): void
    {
        $io->progressStart(6);

        // Disable foreign key checks
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        // Truncate invoices and sales (invoices must be deleted first due to FK)
        $io->text('Truncating invoices and sales...');
        $this->connection->executeStatement('TRUNCATE TABLE comprobante');
        $this->connection->executeStatement('TRUNCATE TABLE movimiento_detalle');
        $this->connection->executeStatement('TRUNCATE TABLE movimiento');
        $io->progressAdvance();

        // Truncate products and stock
        $io->text('Truncating products...');
        $this->connection->executeStatement('TRUNCATE TABLE product_stock');
        $this->connection->executeStatement('TRUNCATE TABLE producto');
        $io->progressAdvance();

        // Delete customers (except default)
        $io->text('Deleting customers...');
        $this->connection->executeStatement("DELETE FROM cliente WHERE ruc != '00000000'");
        $io->progressAdvance();

        // Delete suppliers
        $io->text('Deleting suppliers...');
        $this->connection->executeStatement('TRUNCATE TABLE proveedor');
        $io->progressAdvance();

        // Delete categories (except default)
        $io->text('Deleting categories...');
        $this->connection->executeStatement("DELETE FROM categoria WHERE descripcion != 'General'");
        $io->progressAdvance();

        // Delete units of measure (except NIU)
        $io->text('Deleting units of measure...');
        $this->connection->executeStatement("DELETE FROM unidad WHERE code != 'NIU'");
        $io->progressAdvance();

        // Re-enable foreign key checks
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $io->progressFinish();
    }
}
