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

        // Read from v2 tables only — no cash_shift / cash_movement references
        $sql = <<<SQL
SELECT
    cr.id                                                        AS id,
    cr.name                                                      AS name,
    cs.id                                                        AS cashSessionId,
    cs.opened_at                                                 AS openedAt,
    cs.closed_at                                                 AS closedAt,
    u.username                                                   AS cashier,
    cs.opening_amount
        + COALESCE(SUM(IF(cm.movement_type = 'CASH_IN',  cm.amount, 0)), 0)
        - COALESCE(SUM(IF(cm.movement_type = 'CASH_OUT', cm.amount, 0)), 0)
        + COALESCE(SUM(IF(cm.movement_type = 'SALE_IN',  cm.amount, 0)), 0)
        AS balance,
    IF(cs.id IS NOT NULL AND cs.closed_at IS NULL, 'open', 'closed') AS status
FROM cash_register cr
LEFT JOIN cash_sessions cs
    ON cs.cash_register_id = cr.id
    AND cs.closed_at IS NULL
    AND cs.status = 'OPEN'
LEFT JOIN cash_movements cm
    ON cm.cash_session_id = cs.id
LEFT JOIN usuario u
    ON cs.opened_by = u.usuario_id
WHERE cr.branch_id = ?
GROUP BY cr.id, cs.id
ORDER BY cr.id
SQL;

        // today_sales: grand_total of all sales linked to the open session (all payment methods)
        $salesSql = <<<SQL
SELECT
    cs2.cash_register_id AS cash_register_id,
    COALESCE(SUM(s.grand_total), 0) AS today_sales
FROM cash_sessions cs2
JOIN sales s ON s.cash_session_id = cs2.id
WHERE cs2.branch_id = ?
  AND cs2.closed_at IS NULL
  AND cs2.status = 'OPEN'
GROUP BY cs2.cash_register_id
SQL;

        $data = $this->connection->fetchAllAssociative($sql, [$branchId]);
        $salesData = $this->connection->fetchAllAssociative($salesSql, [$branchId]);

        // Index today_sales by cash_register_id for O(1) lookup
        $salesMap = [];
        foreach ($salesData as $row) {
            $salesMap[(int) $row['cash_register_id']] = (string) $row['today_sales'];
        }

        return array_map(function (array $row) use ($salesMap): CashRegisterDashboardDto {
            $cashRegisterId = (int) $row['id'];
            $todaySalesRaw = $salesMap[$cashRegisterId] ?? '0';

            return new CashRegisterDashboardDto(
                id: $cashRegisterId,
                name: $row['name'],
                cashShiftId: null,
                cashSessionId: $row['cashSessionId'] !== null ? (int) $row['cashSessionId'] : null,
                openedAt: $row['openedAt'] ? new DateTimeImmutable($row['openedAt']) : null,
                closedAt: $row['closedAt'] ? new DateTimeImmutable($row['closedAt']) : null,
                cashier: $row['cashier'],
                balance: (float) ($row['balance'] ?? 0),
                status: $row['status'],
                todaySales: BigDecimal::of($todaySalesRaw)
                    ->toScale(2, RoundingMode::HALF_UP)->__toString(),
            );
        }, $data);
    }
}
