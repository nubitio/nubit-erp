<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Service\CashMovementHydrator;
use ArrayIterator;
use App\Identity\Auth\BranchContextServiceInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Override;

/**
 * Provider for GET /api/cash-movements (top-level collection). Without an
 * explicit provider, API Platform falls back to its default Doctrine ORM
 * collection provider, which reads `CashMovement`'s own mapped table — the
 * frozen legacy `cash_movement` (singular) — instead of the live v2
 * `cash_movements` table application code actually writes to. See
 * ADR-004's amendment for how this was found (fixing it was the
 * prerequisite for using CashMovement reliably from BankReconciliation).
 *
 * @implements ProviderInterface<CashMovement>
 */
final readonly class CashMovementCollectionProvider implements ProviderInterface
{
    private const int DEFAULT_PER_PAGE = 30;

    public function __construct(
        private Connection $connection,
        private CashMovementHydrator $hydrator,
        private BranchContextServiceInterface $branchContext,
    ) {}

    /**
     * @throws Exception
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $filters = $context['filters'] ?? [];
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($filters['itemsPerPage'] ?? self::DEFAULT_PER_PAGE)));
        $branchId = $this->branchContext->getCurrentBranchId();
        $offset = ($page - 1) * $perPage;

        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.id, m.cash_session_id, m.movement_type, m.category_id, m.amount, m.description, m.created_at
             FROM cash_movements m JOIN cash_sessions s ON s.id = m.cash_session_id
             WHERE s.branch_id = ?
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT ? OFFSET ?',
            [$branchId, $perPage, $offset],
            [ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER],
        );

        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM cash_movements m JOIN cash_sessions s ON s.id = m.cash_session_id WHERE s.branch_id = ?', [$branchId]);

        $movements = array_map($this->hydrator->fromRow(...), $rows);

        return new TraversablePaginator(new ArrayIterator($movements), $page, $perPage, $total);
    }
}
