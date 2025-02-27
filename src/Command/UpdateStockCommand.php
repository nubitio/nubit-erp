<?php

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use App\Core\Infrastructure\Service\TenantConnectionSwitcher;
use App\Core\Infrastructure\Service\TenantManager;
use App\Entity\Product;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCommand(
    name: 'app:update-stock',
    description: 'Update stock for all products'
)]
#[AsCronTask(expression: '0 0 * * *')]
class UpdateStockCommand extends TenantAwareCommand
{
    public function __construct(
        TenantManager $tenantManager,
        TenantConnectionSwitcher $tenantConnectionSwitcher,
        LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($tenantManager, $tenantConnectionSwitcher, $logger);
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setHelp(
            'This command allows you to update stock for all products'
        );
    }

    protected function executeTenantCommand(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $batchSize = 100;
        $processed = 0;

        $io->title('Updating stock for all products');
        $io->progressStart();

        $products = $this->entityManager->getRepository(Product::class)
            ->findAll();

        foreach ($products as $product) {
            try {
                $stock = $this->calculateStock($product->getId());
                $currentStock = $product->getStock();
                if ($stock === $currentStock) {
                    continue;
                }
                $this->logger->info(
                    'Updating stock for product ' . $product->getId(),
                    ['stock' => $stock]
                );
                $io->text(
                    'Updating stock for product ' . $product->getId() . ' to '
                    . $stock
                );
                $product->setStock($stock);
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
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @throws Exception
     */
    private function calculateStock($productId): int
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
GROUP BY md.producto_id
SQL;

        $result = $connection->executeQuery($sql, ['productId' => $productId]);

        return (int) $result->fetchOne();
    }
}
