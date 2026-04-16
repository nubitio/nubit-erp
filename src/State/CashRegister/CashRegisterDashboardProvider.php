<?php

declare(strict_types=1);

namespace App\State\CashRegister;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Security\Auth\BranchContextService;
use App\Dto\CashRegister\CashRegisterDashboardDto;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;

/**
 * @implements ProviderInterface<CashRegisterDashboardDto>
 */
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

        if ($branchId === null) {
            return [];
        }

        $sql = <<<SQL
SELECT
    c.id,
    c.name,
    cs.id AS cashShiftId,
    cs.opened_at AS openedAt,
    cs.closed_at AS closedAt,
    u.username AS cashier,
    cs.opening_amount
    + COALESCE(SUM(IF(cm.movement_type = 'CASH_IN',  cm.amount, 0)), 0)
    - COALESCE(SUM(IF(cm.movement_type = 'CASH_OUT', cm.amount, 0)), 0)
    + COALESCE(SUM(IF(cm.movement_type = 'SALE_IN',  cm.amount, 0)), 0)
    AS balance,
    IF(cs.id IS NOT NULL AND cs.status = 'OPEN', 'open', 'closed') AS status,
    COALESCE(SUM(IF(cm.movement_type = 'SALE_IN', cm.amount, 0)), 0) AS today_sales
FROM cash_register c
LEFT JOIN cash_sessions cs ON cs.cash_register_id = c.id AND cs.status = 'OPEN'
LEFT JOIN cash_movements cm ON cm.cash_session_id = cs.id
LEFT JOIN usuario u ON cs.opened_by = u.usuario_id
WHERE c.branch_id = ?
GROUP BY c.id, cs.id, u.username
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
            todaySales: BigDecimal::of((string)($row['today_sales'] ?? '0'))
                ->toScale(2, RoundingMode::HALF_UP)->__toString(),
        ), $data);
    }
}
