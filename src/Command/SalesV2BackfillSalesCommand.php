<?php

declare(strict_types=1);

namespace App\Command;

use App\Core\Application\Command\TenantAwareCommand;
use App\Entity\Sale;
use App\State\Sale\SalesWriteGateway;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sales:v2:backfill-sales',
    description: 'Backfill legacy sales (movimiento tipo_id=2) into Sales V2 tables.',
)]
final class SalesV2BackfillSalesCommand extends TenantAwareCommand
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly SalesWriteGateway $salesWriteGateway,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            'batch-size',
            null,
            InputOption::VALUE_OPTIONAL,
            'Batch size for pending legacy sales.',
            200
        );
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_OPTIONAL,
            'Max number of sales to process in this run (0 = unlimited).',
            0
        );
        $this->addOption(
            'strict',
            null,
            InputOption::VALUE_NONE,
            'Stop on first failed sale instead of continuing.',
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $limit = max(0, (int) $input->getOption('limit'));
        $strict = (bool) $input->getOption('strict');

        try {
            $this->connection->fetchOne('SELECT 1 FROM sales LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM movimiento LIMIT 1');
        } catch (TableNotFoundException $exception) {
            $io->warning('Skipping tenant: Sales V2 or legacy sales tables are not migrated yet.');

            return Command::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        while (true) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $remaining = $limit > 0 ? max(1, $limit - $processed) : $batchSize;
            $fetchLimit = min($batchSize, $remaining);
            $pendingIds = $this->fetchPendingLegacySaleIds($fetchLimit);

            if ($pendingIds === []) {
                break;
            }

            foreach ($pendingIds as $saleId) {
                try {
                    $sale = $this->loadSale($saleId);
                    if (!$sale instanceof Sale) {
                        $io->warning(sprintf('Sale %s not found in ORM load. Skipping.', $saleId));
                        ++$failed;
                        continue;
                    }

                    $cashSessionId = $this->resolveCashSessionId($sale);
                    $this->salesWriteGateway->write($sale, $cashSessionId);
                    ++$processed;
                } catch (\Throwable $exception) {
                    ++$failed;
                    $io->warning(sprintf('Sale %s failed: %s', $saleId, $exception->getMessage()));
                    if ($strict) {
                        throw $exception;
                    }
                } finally {
                    $this->entityManager->clear();
                }

                if ($limit > 0 && $processed >= $limit) {
                    break;
                }
            }
        }

        if ($failed > 0) {
            $io->warning(sprintf('Sales V2 backfill finished with warnings. processed=%d failed=%d', $processed, $failed));
        } else {
            $io->success(sprintf('Sales V2 backfill completed. processed=%d failed=%d', $processed, $failed));
        }

        return $strict && $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function fetchPendingLegacySaleIds(int $limit): array
    {
        $safeLimit = max(1, $limit);
        $rows = $this->connection->fetchFirstColumn(
            sprintf(
                <<<'SQL'
SELECT m.movimiento_id
FROM movimiento m
LEFT JOIN sales s ON s.legacy_movimiento_id = m.movimiento_id
WHERE m.tipo_id = 2
  AND s.id IS NULL
ORDER BY m.date_created ASC, m.movimiento_id ASC
LIMIT %d
SQL,
                $safeLimit
            )
        );

        return array_values(array_map(static fn ($id): string => (string) $id, $rows));
    }

    /**
     * @throws ORMException
     */
    private function loadSale(string $saleId): ?Sale
    {
        return $this->entityManager
            ->getRepository(Sale::class)
            ->createQueryBuilder('s')
            ->leftJoin('s.almacen', 'w')->addSelect('w')
            ->leftJoin('w.branch', 'b')->addSelect('b')
            ->leftJoin('s.cliente', 'c')->addSelect('c')
            ->leftJoin('s.tipo_pago', 'pm')->addSelect('pm')
            ->leftJoin('s.items', 'si')->addSelect('si')
            ->leftJoin('si.producto', 'p')->addSelect('p')
            ->leftJoin('p.unitOfMeasure', 'u')->addSelect('u')
            ->where('s.id = :saleId')
            ->setParameter('saleId', $saleId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function resolveCashSessionId(Sale $sale): int
    {
        $issuedAt = ($sale->getFecha() ?? $sale->getDateCreated() ?? new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $cashRegisterId = $sale->getCajaId();

        if ($cashRegisterId === null) {
            throw new \RuntimeException(sprintf('Sale %s does not have caja_id.', (string) $sale->getId()));
        }

        $cashSessionId = $this->connection->fetchOne(
            <<<'SQL'
SELECT id
FROM cash_sessions
WHERE cash_register_id = :cash_register_id
  AND opened_at <= :issued_at
  AND (closed_at IS NULL OR closed_at >= :issued_at)
ORDER BY
    CASE WHEN status = 'OPEN' THEN 0 ELSE 1 END,
    opened_at DESC
LIMIT 1
SQL,
            [
                'cash_register_id' => $cashRegisterId,
                'issued_at' => $issuedAt,
            ]
        );

        if ($cashSessionId !== false) {
            return (int) $cashSessionId;
        }

        $fallback = $this->connection->fetchOne(
            <<<'SQL'
SELECT id
FROM cash_sessions
WHERE cash_register_id = :cash_register_id
ORDER BY
    CASE WHEN status = 'OPEN' THEN 0 ELSE 1 END,
    opened_at DESC
LIMIT 1
SQL,
            [
                'cash_register_id' => $cashRegisterId,
            ]
        );

        if ($fallback !== false) {
            return (int) $fallback;
        }

        throw new \RuntimeException(sprintf(
            'No cash_session found for sale %s (cash_register_id=%d).',
            (string) $sale->getId(),
            $cashRegisterId
        ));
    }
}
