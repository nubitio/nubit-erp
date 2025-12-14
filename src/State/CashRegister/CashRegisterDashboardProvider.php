<?php

declare(strict_types=1);

namespace App\State\CashRegister;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Security\Auth\BranchContextService;
use App\Dto\CashRegister\CashRegisterDashboardDto;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;

readonly class CashRegisterDashboardProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
        private BranchContextService $branchContext,
    ) {
    }

    /**
     * @inheritdoc
     * @throws Exception|DateMalformedStringException
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
        $branchId = $this->branchContext->getCurrentBranchId();

        $sql = <<<SQL
SELECT
    c.id,
    c.name,
    cs.id AS cashShiftId,
    cs.opened_at as openedAt,
    cs.closed_at as closedAt,
    u.username AS cashier,
    cs.opening_balance +
    COALESCE(SUM(IF(cm.type = 'income', cm.amount, 0)), 0) -
    COALESCE(SUM(IF(cm.type = 'expense', cm.amount, 0)), 0)
    AS balance,
    IF(cs.id IS NOT NULL AND cs.closed_at IS NULL, 'open', 'closed') AS status
FROM cash_register c
LEFT JOIN cash_shift cs ON cs.cash_register_id = c.id AND cs.closed_at IS NULL
LEFT JOIN cash_movement cm ON cm.cash_shift_id = cs.id
LEFT JOIN usuario u ON cs.opened_by = u.usuario_id
WHERE c.branch_id = ?
GROUP BY c.id, cs.id
ORDER BY c.id
SQL;

        $data = $this->connection->fetchAllAssociative($sql, [$branchId]);

        return array_map(fn (array $row): CashRegisterDashboardDto => new CashRegisterDashboardDto(
            id: (int)$row['id'],
            name: $row['name'],
            cashShiftId: $row['cashShiftId'] ? (int)$row['cashShiftId'] : null,
            openedAt: $row['openedAt'] ? new DateTimeImmutable($row['openedAt']) : null,
            closedAt: $row['closedAt'] ? new DateTimeImmutable($row['closedAt']) : null,
            cashier: $row['cashier'],
            balance: (float)$row['balance'],
            status: $row['status'],
        ), $data);
    }
}
