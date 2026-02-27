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
    private ?int $fallbackSellerUserId = null;

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

                    $this->normalizeLegacySaleForBackfill($sale);
                    $cashContext = $this->resolveCashContext($sale);
                    $sale->setCajaId($cashContext['cashRegisterId']);
                    $sale->setCreatedBy($this->resolveSellerUserId($sale));
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

    private function resolveSellerUserId(Sale $sale): int
    {
        $createdBy = $this->safeGetUserId(static fn (Sale $item): ?int => $item->getCreatedBy(), $sale);
        $updatedBy = $this->safeGetUserId(static fn (Sale $item): ?int => $item->getUpdatedBy(), $sale);
        $candidateIds = array_values(
            array_filter(
                [$createdBy, $updatedBy],
                static fn (?int $value): bool => $value !== null
            )
        );

        foreach ($candidateIds as $candidateId) {
            $exists = $this->connection->fetchOne(
                'SELECT 1 FROM usuario WHERE usuario_id = :user_id LIMIT 1',
                ['user_id' => $candidateId]
            );
            if ($exists !== false) {
                return (int) $candidateId;
            }
        }

        if ($this->fallbackSellerUserId === null) {
            $fallback = $this->connection->fetchOne('SELECT MIN(usuario_id) FROM usuario');
            if ($fallback === false || $fallback === null) {
                throw new \RuntimeException(sprintf(
                    'No users found in usuario table to backfill seller for sale %s.',
                    (string) $sale->getId()
                ));
            }
            $this->fallbackSellerUserId = (int) $fallback;
        }

        return $this->fallbackSellerUserId;
    }

    /**
     * Legacy rows can contain NULLs on non-nullable mapped fields, which raises Error on typed properties.
     */
    private function safeGetUserId(callable $getter, Sale $sale): ?int
    {
        try {
            $value = $getter($sale);
        } catch (\Error) {
            return null;
        }

        return $value;
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
        $issuedAtDate = $this->safeGetDate(static fn (Sale $item): ?\DateTimeImmutable => $item->getFecha(), $sale)
            ?? $this->safeGetDate(static fn (Sale $item): ?\DateTimeImmutable => $item->getDateCreated(), $sale)
            ?? new \DateTimeImmutable();
        $issuedAt = $issuedAtDate->format('Y-m-d H:i:s');
        $cashRegisterId = $sale->getCajaId();
        $branchId = $this->resolveBranchIdForSale($sale);

        if ($cashRegisterId !== null) {
            $cashSessionId = $this->connection->fetchOne(
                <<<'SQL'
SELECT id
FROM cash_sessions
WHERE cash_register_id = :cash_register_id
  AND (:branch_id IS NULL OR branch_id = :branch_id)
  AND opened_at <= :issued_at
  AND (closed_at IS NULL OR closed_at >= :issued_at)
ORDER BY
    CASE WHEN status = 'OPEN' THEN 0 ELSE 1 END,
    opened_at DESC
LIMIT 1
SQL,
                [
                    'cash_register_id' => $cashRegisterId,
                    'branch_id' => $branchId,
                    'issued_at' => $issuedAt,
                ]
            );

            if ($cashSessionId === false) {
                $cashSessionId = $this->connection->fetchOne(
                    <<<'SQL'
SELECT id
FROM cash_sessions
WHERE cash_register_id = :cash_register_id
  AND (:branch_id IS NULL OR branch_id = :branch_id)
ORDER BY
    CASE WHEN status = 'OPEN' THEN 0 ELSE 1 END,
    opened_at DESC
LIMIT 1
SQL,
                    [
                        'cash_register_id' => $cashRegisterId,
                        'branch_id' => $branchId,
                    ]
                );
            }

            if ($cashSessionId !== false) {
                return [
                    'cashSessionId' => (int) $cashSessionId,
                    'cashRegisterId' => $cashRegisterId,
                ];
            }

            $legacyShiftId = $this->connection->fetchOne(
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

            if ($legacyShiftId !== false) {
                return $this->upsertCashSessionFromLegacyShift((int) $legacyShiftId, $branchId);
            }
        }

        if ($branchId !== null) {
            $row = $this->connection->fetchAssociative(
                <<<'SQL'
SELECT cs.id AS cash_session_id, cs.cash_register_id
FROM cash_sessions cs
WHERE cs.branch_id = :branch_id
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
            if (is_array($row) && isset($row['cash_session_id'], $row['cash_register_id'])) {
                return [
                    'cashSessionId' => (int) $row['cash_session_id'],
                    'cashRegisterId' => (int) $row['cash_register_id'],
                ];
            }
        }

        if ($branchId !== null) {
            $row = $this->connection->fetchAssociative(
                <<<'SQL'
SELECT cs.id AS cash_session_id, cs.cash_register_id
FROM cash_sessions cs
WHERE cs.branch_id = :branch_id
ORDER BY
    CASE WHEN cs.status = 'OPEN' THEN 0 ELSE 1 END,
    cs.opened_at DESC
LIMIT 1
SQL,
                [
                    'branch_id' => $branchId,
                ]
            );
            if (is_array($row) && isset($row['cash_session_id'], $row['cash_register_id'])) {
                return [
                    'cashSessionId' => (int) $row['cash_session_id'],
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

        $openedBy = $this->resolveSellerUserId($sale);

        $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO cash_sessions (
    legacy_cash_shift_id,
    cash_register_id,
    branch_id,
    opened_by,
    status,
    opening_amount,
    opened_at,
    closed_at,
    expected_amount,
    counted_amount,
    difference_amount,
    close_note
) VALUES (
    NULL,
    :cash_register_id,
    :branch_id,
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
                'branch_id' => $branchId,
                'opened_by' => $openedBy,
                'opened_at' => $issuedAt,
                'closed_at' => $issuedAt,
            ]
        );
        $cashSessionId = $this->connection->lastInsertId();

        return [
            'cashSessionId' => (int) $cashSessionId,
            'cashRegisterId' => (int) $fallbackRegisterId,
        ];
    }

    /**
     * @return array{cashSessionId: int, cashRegisterId: int}
     */
    private function upsertCashSessionFromLegacyShift(int $legacyShiftId, ?string $branchId): array
    {
        $existing = $this->connection->fetchAssociative(
            'SELECT id, cash_register_id FROM cash_sessions WHERE legacy_cash_shift_id = ? LIMIT 1',
            [$legacyShiftId]
        );
        if (is_array($existing) && isset($existing['id'], $existing['cash_register_id'])) {
            return [
                'cashSessionId' => (int) $existing['id'],
                'cashRegisterId' => (int) $existing['cash_register_id'],
            ];
        }

        $legacyShift = $this->connection->fetchAssociative(
            <<<'SQL'
SELECT
    id,
    cash_register_id,
    opened_by,
    closed_by,
    status,
    opening_balance,
    expected_amount,
    counted_amount,
    difference,
    opened_at,
    closed_at,
    branch_id,
    note
FROM cash_shift
WHERE id = ?
LIMIT 1
SQL,
            [$legacyShiftId]
        );

        if (!is_array($legacyShift) || !isset($legacyShift['cash_register_id'], $legacyShift['opened_by'])) {
            throw new \RuntimeException(sprintf(
                'No se pudo cargar cash_shift %d para generar cash_session.',
                $legacyShiftId
            ));
        }

        $resolvedBranchId = $branchId;
        if ($resolvedBranchId === null || trim($resolvedBranchId) === '') {
            $resolvedBranchId = (string) ($legacyShift['branch_id'] ?? '');
        }
        if ($resolvedBranchId === '') {
            $resolvedBranchId = (string) $this->connection->fetchOne(
                'SELECT branch_id FROM cash_register WHERE id = ? LIMIT 1',
                [(int) $legacyShift['cash_register_id']]
            );
        }

        $statusRaw = strtoupper((string) ($legacyShift['status'] ?? 'CLOSED'));
        $status = $statusRaw === 'OPEN' ? 'OPEN' : 'CLOSED';

        $this->connection->executeStatement(
            <<<'SQL'
INSERT INTO cash_sessions (
    legacy_cash_shift_id,
    cash_register_id,
    branch_id,
    opened_by,
    closed_by,
    status,
    opening_amount,
    expected_amount,
    counted_amount,
    difference_amount,
    opened_at,
    closed_at,
    close_note
) VALUES (
    :legacy_cash_shift_id,
    :cash_register_id,
    :branch_id,
    :opened_by,
    :closed_by,
    :status,
    :opening_amount,
    :expected_amount,
    :counted_amount,
    :difference_amount,
    :opened_at,
    :closed_at,
    :close_note
)
SQL,
            [
                'legacy_cash_shift_id' => $legacyShiftId,
                'cash_register_id' => (int) $legacyShift['cash_register_id'],
                'branch_id' => $resolvedBranchId,
                'opened_by' => (int) $legacyShift['opened_by'],
                'closed_by' => $legacyShift['closed_by'] !== null ? (int) $legacyShift['closed_by'] : null,
                'status' => $status,
                'opening_amount' => $legacyShift['opening_balance'] ?? 0,
                'expected_amount' => $legacyShift['expected_amount'] ?? 0,
                'counted_amount' => $legacyShift['counted_amount'] ?? 0,
                'difference_amount' => $legacyShift['difference'] ?? 0,
                'opened_at' => $legacyShift['opened_at'] ?? (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'closed_at' => $legacyShift['closed_at'],
                'close_note' => (string) ($legacyShift['note'] ?? 'Imported from legacy cash_shift'),
            ]
        );

        return [
            'cashSessionId' => (int) $this->connection->lastInsertId(),
            'cashRegisterId' => (int) $legacyShift['cash_register_id'],
        ];
    }

    private function resolveBranchIdForSale(Sale $sale): ?string
    {
        $branchId = $sale->getBranch()?->getId() ?? $sale->getAlmacen()?->getBranch()?->getId();
        if ($branchId !== null && trim($branchId) !== '') {
            return $branchId;
        }

        $saleId = $sale->getId();
        if ($saleId !== null && trim($saleId) !== '') {
            $row = $this->connection->fetchAssociative(
                <<<'SQL'
SELECT m.branch_id, a.local_id AS warehouse_branch_id
FROM movimiento m
LEFT JOIN almacen a ON a.almacen_id = m.almacen_id
WHERE m.movimiento_id = :sale_id
LIMIT 1
SQL,
                ['sale_id' => $saleId]
            );
            if (is_array($row)) {
                $fromMovimiento = isset($row['branch_id']) ? trim((string) $row['branch_id']) : '';
                if ($fromMovimiento !== '') {
                    return $fromMovimiento;
                }

                $fromWarehouse = isset($row['warehouse_branch_id']) ? trim((string) $row['warehouse_branch_id']) : '';
                if ($fromWarehouse !== '') {
                    return $fromWarehouse;
                }
            }
        }

        $fallbackBranchId = $this->connection->fetchOne('SELECT local_id FROM local ORDER BY local_id ASC LIMIT 1');
        if ($fallbackBranchId === false || $fallbackBranchId === null) {
            return null;
        }

        return (string) $fallbackBranchId;
    }

    private function normalizeLegacySaleForBackfill(Sale $sale): void
    {
        $fecha = $this->safeGetDate(static fn (Sale $item): ?\DateTimeImmutable => $item->getFecha(), $sale);
        $dateCreated = $this->safeGetDate(
            static fn (Sale $item): ?\DateTimeImmutable => $item->getDateCreated(),
            $sale
        );
        $fallbackDate = new \DateTimeImmutable();

        if ($fecha === null) {
            $sale->setFecha($dateCreated ?? $fallbackDate);
        }

        if ($dateCreated === null) {
            $sale->setDateCreated($fecha ?? $fallbackDate);
        }
    }

    private function safeGetDate(callable $getter, Sale $sale): ?\DateTimeImmutable
    {
        try {
            $value = $getter($sale);
        } catch (\Error) {
            return null;
        }

        return $value;
    }
}
