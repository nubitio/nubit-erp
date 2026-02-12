<?php

declare(strict_types=1);

namespace App\Command;

use Override;
use Exception;
use App\Core\Application\Command\TenantAwareCommand;
use App\Entity\Product;
use App\Service\ProductStockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:initialize-warehouse-stock',
    description: 'Initialize warehouse-specific stock based on current global stock'
)]
class InitializeWarehouseStockCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductStockService $productStockService,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be done without actually making changes'
            );
    }

    #[Override]
    protected function executeTenantCommand(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $batchSize = 100;
        $processed = 0;

        if ($dryRun) {
            $io->note('Running in dry-run mode. No changes will be made.');
        }

        $io->title('Initializing warehouse-specific stock');
        $io->progressStart();

        $products = $this->entityManager->getRepository(Product::class)->findAll();

        if ($products === []) {
            $io->warning('No products found. Skipping initialization.');
            return Command::SUCCESS;
        }

        foreach ($products as $product) {
            try {
                if ($dryRun) {
                    $io->text(
                        sprintf(
                            'Would distribute global stock %s for product %s across warehouses',
                            $product->getStock(),
                            $product->getName()
                        )
                    );
                } else {
                    $this->productStockService->distributeGlobalStockToWarehouses($product);
                    $io->text(
                        sprintf(
                            'Distributed global stock %s for product %s across warehouses',
                            $product->getStock(),
                            $product->getName()
                        )
                    );
                }
            } catch (Exception $e) {
                $this->logger->error(
                    'An error occurred while initializing stock for product ' . $product->getId(),
                    ['exception' => $e]
                );
                $io->error(
                    'An error occurred while initializing stock for product ' . $product->getId()
                );
            }

            ++$processed;
            $io->progressAdvance();
            if (!$dryRun && ($processed % $batchSize) === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();

                // Reload products after clearing entity manager
                if ($processed < count($products)) {
                    $products = $this->entityManager->getRepository(Product::class)->findAll();
                }
            }
        }

        if (!$dryRun) {
            // Final flush for any remaining entities
            $this->entityManager->flush();
            $io->success('Warehouse-specific stock has been initialized successfully.');
        } else {
            $io->success('Dry run completed. No changes were made.');
        }

        return Command::SUCCESS;
    }
}
