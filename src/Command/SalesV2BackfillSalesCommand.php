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

                    $cashContext = $this->resolveCashContext($sale);
                    $sale->setCajaId($cashContext['cashRegisterId']);
                    $cashSessionId = $cashContext['cashSessionId'];
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
            $io->warning(
                sprintf('Sales V2 backfill finished with warnings. processed=%d failed=%d', $processed, $failed)
            );
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

        return array_map(static fn ($id): string => (string) $id, $rows);
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

    /**
     * @return array{cashSessionId: int, cashRegisterId: int}
     */
    private function resolveCashContext(Sale $sale): array
    {
        $issuedAt = ($sale->getFecha() ?? $sale->getDateCreated() ?? new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $cashRegisterId = $sale->getCajaId();
        $branchId = $sale->getBranch()?->getId() ?? $sale->getAlmacen()?->getBranch()?->getId();

        if ($cashRegisterId !== null) {
            $cashShiftId = $this->connection->fetchOne(
                <<<'SQL'
SELECT id
FROM cash_shift
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

            if ($cashShiftId === false) {
                $cashShiftId = $this->connection->fetchOne(
                    <<<'SQL'
SELECT id
FROM cash_shift
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
            }

            if ($cashShiftId !== false) {
                return [
                    'cashSessionId' => (int) $cashShiftId,
                    'cashRegisterId' => $cashRegisterId,
                ];
            }
        }

        if ($branchId !== null) {
            $row = $this->connection->fetchAssociative(
                <<<'SQL'
SELECT cs.id AS cash_shift_id, cr.id AS cash_register_id
FROM cash_shift cs
INNER JOIN cash_register cr ON cr.id = cs.cash_register_id
WHERE cr.branch_id = :branch_id
  AND cs.opened_at <= :issued_at
  AND (cs.closed_at IS NULL OR cs.closed_at >= :issued_at)
ORDER BY
    CASE WHEN cs.status = 'OPEN' THEN 0 ELSE 1 END,
    cs.opened_at DESC
LIMIT 1
SQL,
                [
                    'branch_id' => $branchId,
                    'issued_at' => $issuedAt,
                ]
            );
            if (is_array($row) && isset($row['cash_shift_id'], $row['cash_register_id'])) {
                return [
                    'cashSessionId' => (int) $row['cash_shift_id'],
                    'cashRegisterId' => (int) $row['cash_register_id'],
                ];
            }
        }

        if ($branchId !== null) {
            $row = $this->connection->fetchAssociative(
                <<<'SQL'
SELECT cs.id AS cash_shift_id, cr.id AS cash_register_id
FROM cash_shift cs
INNER JOIN cash_register cr ON cr.id = cs.cash_register_id
WHERE cr.branch_id = :branch_id
ORDER BY
    CASE WHEN cs.status = 'OPEN' THEN 0 ELSE 1 END,
    cs.opened_at DESC
LIMIT 1
SQL,
                [
                    'branch_id' => $branchId,
                ]
            );
            if (is_array($row) && isset($row['cash_shift_id'], $row['cash_register_id'])) {
                return [
                    'cashSessionId' => (int) $row['cash_shift_id'],
                    'cashRegisterId' => (int) $row['cash_register_id'],
                ];
            }
        }

        if ($branchId === null) {
            throw new \RuntimeException(sprintf(
                'No se pudo resolver branch_id para la venta %s sin caja_id.',
                (string) $sale->getId()
            ));
        }

        $fallbackRegisterId = $this->connection->fetchOne(
            <<<'SQL'
SELECT id
FROM cash_register
WHERE branch_id = :branch_id
ORDER BY id ASC
LIMIT 1
SQL,
            [
                'branch_id' => $branchId,
            ]
        );

        if ($fallbackRegisterId === false) {
            $this->connection->executeStatement(
                'INSERT INTO cash_register (branch_id, name) VALUES (:branch_id, :name)',
                [
                    'branch_id' => $branchId,
                    'name' => 'MIGRATION_AUTO',
                ]
            );
            $fallbackRegisterId = $this->connection->lastInsertId();
        }

        $openedBy = $sale->getCreatedBy() ?? $sale->getUpdatedBy();
        if ($openedBy === null) {
            $openedById = $this->connection->fetchOne('SELECT MIN(usuario_id) FROM usuario');
            if ($openedById === false || $openedById === null) {
                throw new \RuntimeException(sprintf(
                    'No users found to create fallback cash_shift for sale %s.',
                    (string) $sale->getId()
                ));
            }
            $openedBy = (int) $openedById;
        }

        $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO cash_shift (
    cash_register_id,
    opened_by,
    status,
    opening_balance,
    opened_at,
    closed_at,
    expected_amount,
    counted_amount,
    difference,
    note
) VALUES (
    :cash_register_id,
    :opened_by,
    'CLOSED',
    0.00,
    :opened_at,
    :closed_at,
    0.00,
    0.00,
    0.00,
    'Auto-generated by sales v2 backfill for tenants without cashbox flow'
)
SQL,
            [
                'cash_register_id' => (int) $fallbackRegisterId,
                'opened_by' => $openedBy,
                'opened_at' => $issuedAt,
                'closed_at' => $issuedAt,
            ]
        );
        $cashShiftId = $this->connection->lastInsertId();

        return [
            'cashSessionId' => (int) $cashShiftId,
            'cashRegisterId' => (int) $fallbackRegisterId,
        ];
    }
}
