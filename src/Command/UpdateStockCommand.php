<?php

declare(strict_types=1);

namespace App\Command;

use Override;
use App\Core\Application\Command\TenantAwareCommand;
use App\Core\Service\SettingsService;
use App\Core\Util\Constants;
use App\Entity\Product;
use App\Entity\Warehouse;
use App\Service\ProductStockService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(name: 'app:update-stock', description: '[DEPRECATED] Legacy stock rebuild from movimiento', help: <<<'TXT'
This command rebuilds stock from legacy movimiento tables.
Deprecated after POS V2 cutover; use only for controlled legacy recovery.
TXT)]
#[AsCronTask(expression: '0 0 * * *')]
class UpdateStockCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductStockService $productStockService,
        private readonly SettingsService $settings,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            'force-legacy',
            null,
            InputOption::VALUE_NONE,
            'Allow legacy stock rebuild from movimiento tables (deprecated path).'
        );
    }

    #[Override]
    protected function executeTenantCommand(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        if (!$this->isLegacyExecutionAllowed($input)) {
            $io->warning('Skipping deprecated legacy stock rebuild. Use --force-legacy to run intentionally.');
            return Command::SUCCESS;
        }

        $batchSize = 100;
        $processed = 0;

        $io->title('Updating stock for all products in all warehouses');
        $io->progressStart();

        $products = $this->entityManager->getRepository(Product::class)->findAll();
        $warehouses = $this->entityManager->getRepository(Warehouse::class)->findAll();

        if ($warehouses === []) {
            $io->warning('No warehouses found. Skipping stock update.');
            return Command::SUCCESS;
        }

        foreach ($products as $product) {
            try {
                // Update warehouse-specific stock
                foreach ($warehouses as $warehouse) {
                    $warehouseStock = $this->calculateStock($product->getId(), $warehouse->getId());

                    $this->productStockService->updateStockForProductInWarehouse(
                        $product,
                        $warehouse,
                        $warehouseStock
                    );

                    $this->logger->info(
                        'Updated stock for product in warehouse',
                        [
                            'product' => $product->getId(),
                            'warehouse' => $warehouse->getId(),
                            'stock' => $warehouseStock
                        ]
                    );
                    $io->text(
                        sprintf(
                            'Updated stock for product %s in warehouse %s to %s',
                            $product->getId(),
                            $warehouse->getName(),
                            $warehouseStock
                        )
                    );
                }

                // Update global stock (sum of all warehouse stocks)
                $globalStock = $this->calculateStock($product->getId());
                $globalStockStr = number_format($globalStock, 2, '.', '');
                $currentStock = $product->getStock();

                if ($globalStockStr !== $currentStock) {
                    $product->setStock($globalStockStr);
                    $this->logger->info(
                        'Updated global stock for product',
                        [
                            'product' => $product->getId(),
                            'stock' => $globalStock
                        ]
                    );
                    $io->text(
                        sprintf(
                            'Updated global stock for product %s to %s',
                            $product->getId(),
                            $globalStock
                        )
                    );
                }
            } catch (Exception $e) {
                $this->logger->error(
                    'An error occurred while calculating stock for product '
                    . $product->getId(),
                    ['exception' => $e]
                );
                $io->error(
                    'An error occurred while calculating stock for product '
                    . $product->getId()
                );
            }

            ++$processed;
            $io->progressAdvance();
            if (($processed % $batchSize) === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();

                // Reload repositories after clearing entity manager
                if ($processed < count($products)) {
                    $products = $this->entityManager->getRepository(Product::class)->findAll();
                    $warehouses = $this->entityManager->getRepository(Warehouse::class)->findAll();
                }
            }
        }

        // Final flush for any remaining entities
        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    private function isLegacyExecutionAllowed(InputInterface $input): bool
    {
        if ((bool) $input->getOption('force-legacy')) {
            return true;
        }

        $isAuthoritative = strtolower(trim((string) $this->settings->get(Constants::SALES_V2_MODE, 'dual_write')))
            === 'v2_authoritative';
        $legacyProjectionDisabled = filter_var(
            $this->settings->get('sales_v2_disable_legacy_stock_projection', 'false'),
            FILTER_VALIDATE_BOOL
        );

        return !$isAuthoritative && !$legacyProjectionDisabled;
    }

    /**
     * @throws Exception
     */
    private function calculateStock(string $productId, ?string $warehouseId = null): float
    {
        $connection = $this->entityManager->getConnection();

        $sql = <<<SQL
SELECT
    COALESCE(SUM(IF(m.tipo_id = 1,  md.cantidad, 0)), 0) -
    COALESCE(SUM(IF(m.tipo_id = 2, md.cantidad, 0)), 0)
    AS stock
FROM movimiento_detalle md
INNER JOIN movimiento m ON m.movimiento_id = md.movimiento_id
WHERE md.producto_id = :productId
AND m.estado = 1
SQL;

        $params = ['productId' => $productId];

        if ($warehouseId !== null) {
            $sql .= " AND m.almacen_id = :warehouseId";
            $params['warehouseId'] = $warehouseId;
        }

        $sql .= " GROUP BY md.producto_id";

        $result = $connection->executeQuery($sql, $params);
        $stockValue = $result->fetchOne();

        return $stockValue ? (float) $stockValue : 0.0;
    }
}
