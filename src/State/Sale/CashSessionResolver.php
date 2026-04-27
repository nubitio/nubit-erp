<?php

declare(strict_types=1);

namespace App\State\Sale;

use App\Core\Exception\ServiceException;
use App\Sales\Entity\SaleRecord;
use Doctrine\DBAL\Connection;

/**
 * Resolves and validates the canonical cash session for Sales V2 writes.
 */
final readonly class CashSessionResolver implements CashSessionResolverInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws ServiceException
     */
    public function resolveForSale(SaleRecord $sale): int
    {
        $saleId = (string) ($sale->getId() ?? 'NEW');
        $branchId = $sale->getBranch()?->getId() ?? $sale->getAlmacen()?->getBranch()?->getId();
        if ($branchId === null) {
            throw new ServiceException(sprintf(
                'No se pudo resolver cash_session_id para la venta %s: sucursal no definida.',
                $saleId
            ));
        }

        $cashRegisterId = $sale->getCajaId();
        $rawIssuedAt = $sale->getFecha() ?? $sale->getDateCreated() ?? new \DateTimeImmutable();
        $issuedAt = \DateTimeImmutable::createFromInterface($rawIssuedAt);
        $issuedAtSql = $issuedAt->format('Y-m-d H:i:s');

        $candidate = $cashRegisterId !== null
            ? $this->findByRegisterAndIssuedAt($cashRegisterId, $branchId, $issuedAtSql)
            : false;

        if ($candidate === false) {
            $candidate = $this->findByBranchAndIssuedAt($branchId, $issuedAtSql);
        }

        if ($candidate === false) {
            $candidate = $this->findLatestByBranch($branchId);
        }

        if ($candidate === false) {
            throw new ServiceException(sprintf(
                'No se pudo resolver cash_session_id para la venta %s.',
                $saleId
            ));
        }

        $this->validateCandidate($candidate, $branchId, $cashRegisterId, $issuedAt, $saleId);

        return (int) $candidate['id'];
    }

    /**
     * Like resolveForSale() but returns null instead of throwing when no cash session is found.
     * Use this when the cash register is not required (REQUIRE_CASH_REGISTER=false).
     *
     * @throws ServiceException when the branch cannot be determined (infrastructure error)
     */
    public function tryResolveForSale(SaleRecord $sale): ?int
    {
        $saleId = (string) ($sale->getId() ?? 'NEW');
        $branchId = $sale->getBranch()?->getId() ?? $sale->getAlmacen()?->getBranch()?->getId();
        if ($branchId === null) {
            throw new ServiceException(sprintf(
                'No se pudo resolver cash_session_id para la venta %s: sucursal no definida.',
                $saleId
            ));
        }

        $cashRegisterId = $sale->getCajaId();
        $rawIssuedAt = $sale->getFecha() ?? $sale->getDateCreated() ?? new \DateTimeImmutable();
        $issuedAt = \DateTimeImmutable::createFromInterface($rawIssuedAt);
        $issuedAtSql = $issuedAt->format('Y-m-d H:i:s');

        $candidate = $cashRegisterId !== null
            ? $this->findByRegisterAndIssuedAt($cashRegisterId, $branchId, $issuedAtSql)
            : false;

        if ($candidate === false) {
            $candidate = $this->findByBranchAndIssuedAt($branchId, $issuedAtSql);
        }

        if ($candidate === false) {
            $candidate = $this->findLatestByBranch($branchId);
        }

        if ($candidate === false) {
            return null;
        }

        $this->validateCandidate($candidate, $branchId, $cashRegisterId, $issuedAt, $saleId);

        return (int) $candidate['id'];
    }

    /**
     * @return array<string, mixed>|false
     */
    private function findByRegisterAndIssuedAt(int $cashRegisterId, string $branchId, string $issuedAt): array|false
    {
        return $this->connection->fetchAssociative(
            <<<'SQL'
SELECT id, cash_register_id, branch_id, opened_at, closed_at
FROM cash_sessions
WHERE cash_register_id = :cash_register_id
  AND branch_id = :branch_id
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
    }

    /**
     * @return array<string, mixed>|false
     */
    private function findByBranchAndIssuedAt(string $branchId, string $issuedAt): array|false
    {
        return $this->connection->fetchAssociative(
            <<<'SQL'
SELECT id, cash_register_id, branch_id, opened_at, closed_at
FROM cash_sessions
WHERE branch_id = :branch_id
  AND opened_at <= :issued_at
  AND (closed_at IS NULL OR closed_at >= :issued_at)
ORDER BY
    CASE WHEN status = 'OPEN' THEN 0 ELSE 1 END,
    opened_at DESC
LIMIT 1
SQL,
            [
                'branch_id' => $branchId,
                'issued_at' => $issuedAt,
            ]
        );
    }

    /**
     * @return array<string, mixed>|false
     */
    private function findLatestByBranch(string $branchId): array|false
    {
        return $this->connection->fetchAssociative(
            <<<'SQL'
SELECT id, cash_register_id, branch_id, opened_at, closed_at
FROM cash_sessions
WHERE branch_id = :branch_id
  AND status = 'OPEN'
ORDER BY opened_at DESC
LIMIT 1
SQL,
            [
                'branch_id' => $branchId,
            ]
        );
    }

    /**
     * Fetches the cash_register_id for an already-resolved cash session.
     * Uses a single targeted lookup against the cash_sessions table (V2 canonical source).
     * Returns null if the row does not exist or cash_register_id is not set.
     */
    public function resolveRegisterIdForSession(int $sessionId): ?int
    {
        $row = $this->connection->fetchAssociative(
            'SELECT cash_register_id FROM cash_sessions WHERE id = :id',
            ['id' => $sessionId]
        );

        if ($row === false || empty($row['cash_register_id'])) {
            return null;
        }

        return (int) $row['cash_register_id'];
    }

    /**
     * @param array<string, mixed> $candidate
     * @throws ServiceException
     */
    private function validateCandidate(
        array $candidate,
        string $expectedBranchId,
        ?int $expectedCashRegisterId,
        \DateTimeImmutable $issuedAt,
        string $saleId
    ): void {
        $candidateId = (int) ($candidate['id'] ?? 0);
        $candidateBranchId = (string) ($candidate['branch_id'] ?? '');
        $candidateRegisterId = (int) ($candidate['cash_register_id'] ?? 0);
        $openedAtRaw = (string) ($candidate['opened_at'] ?? '');
        $closedAtRaw = $candidate['closed_at'] ?? null;

        if ($candidateId <= 0) {
            throw new ServiceException(sprintf(
                'Cash session inválida al procesar venta %s.',
                $saleId
            ));
        }

        if ($candidateBranchId !== $expectedBranchId) {
            throw new ServiceException(sprintf(
                'La sesión de caja %d no pertenece a la sucursal de la venta %s.',
                $candidateId,
                $saleId
            ));
        }

        if ($expectedCashRegisterId !== null && $candidateRegisterId !== $expectedCashRegisterId) {
            throw new ServiceException(sprintf(
                'La sesión de caja %d no pertenece a la caja esperada para la venta %s.',
                $candidateId,
                $saleId
            ));
        }

        if ($openedAtRaw !== '') {
            $openedAt = new \DateTimeImmutable($openedAtRaw);
            if ($issuedAt < $openedAt) {
                throw new ServiceException(sprintf(
                    'La sesión de caja %d abre después de la fecha/hora de la venta %s.',
                    $candidateId,
                    $saleId
                ));
            }
        }

        if (is_string($closedAtRaw) && $closedAtRaw !== '') {
            $closedAt = new \DateTimeImmutable($closedAtRaw);
            if ($issuedAt > $closedAt) {
                throw new ServiceException(sprintf(
                    'La sesión de caja %d ya estaba cerrada para la fecha/hora de la venta %s.',
                    $candidateId,
                    $saleId
                ));
            }
        }
    }
}
