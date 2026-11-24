<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Dto\CashRegisterDashboardDto;
use App\Identity\Auth\BranchContextServiceInterface;
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
        private BranchContextServiceInterface $branchContext,
    ) {}

    /**
     * @return list<CashRegisterDashboardDto>
     * @throws Exception|DateMalformedStringException
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $branchId = $this->branchContext->getCurrentBranchId();

        if ($branchId === null) {
            return [];
        }

        $sql = <<<SQL
            SELECT
                c.id,
                c.name,
                cs.id AS cashSessionId,
                cs.opened_at AS openedAt,
                cs.closed_at AS closedAt,
                u.username AS cashier,
                cs.opening_amount
                + COALESCE(SUM(IF(cm.movement_type = 'CASH_IN',  cm.amount, 0)), 0)
                - COALESCE(SUM(IF(cm.movement_type = 'CASH_OUT', cm.amount, 0)), 0)
                - COALESCE(SUM(IF(cm.movement_type = 'REFUND_OUT', cm.amount, 0)), 0)
                + COALESCE(SUM(IF(cm.movement_type = 'SALE_IN',  cm.amount, 0)), 0)
                AS balance,
                IF(cs.id IS NOT NULL AND cs.status = 'OPEN', 'open', 'closed') AS status,
                COALESCE(SUM(IF(cm.movement_type = 'SALE_IN', cm.amount, 0)), 0) AS today_sales,
                (SELECT last.closed_at FROM cash_sessions last
                    WHERE last.cash_register_id = c.id AND last.status = 'CLOSED'
                    ORDER BY last.closed_at DESC, last.id DESC LIMIT 1) AS lastClosedAt,
                (SELECT last.difference_amount FROM cash_sessions last
                    WHERE last.cash_register_id = c.id AND last.status = 'CLOSED'
                    ORDER BY last.closed_at DESC, last.id DESC LIMIT 1) AS lastDifference
            FROM cash_register c
            LEFT JOIN cash_sessions cs ON cs.cash_register_id = c.id AND cs.status = 'OPEN'
            LEFT JOIN cash_movements cm ON cm.cash_session_id = cs.id
            LEFT JOIN usuario u ON cs.opened_by = u.usuario_id
            WHERE c.branch_id = ?
            GROUP BY c.id, cs.id, u.username
            ORDER BY c.id
            SQL;

        $data = $this->connection->fetchAllAssociative($sql, [$branchId]);

        return array_map(
            fn(array $row): CashRegisterDashboardDto => new CashRegisterDashboardDto(
                id: (int) $row['id'],
                name: $row['name'],
                cashSessionId: $row['cashSessionId'] ? (int) $row['cashSessionId'] : null,
                cashShiftId: $row['cashSessionId'] ? (int) $row['cashSessionId'] : null,
                openedAt: $row['openedAt'] ? new DateTimeImmutable($row['openedAt']) : null,
                closedAt: $row['closedAt'] ? new DateTimeImmutable($row['closedAt']) : null,
                cashier: $row['cashier'],
                balance: (float) $row['balance'],
                status: $row['status'],
                todaySales: BigDecimal::of((string) ($row['today_sales'] ?? '0'))->toScale(
                    2,
                    RoundingMode::HALF_UP,
                )->__toString(),
                lastClosedAt: ($row['lastClosedAt'] ?? null) !== null
                    ? new DateTimeImmutable((string) $row['lastClosedAt'])
                    : null,
                lastDifference: ($row['lastDifference'] ?? null) === null
                    ? null
                    : BigDecimal::of((string) $row['lastDifference'])->toScale(2, RoundingMode::HALF_UP)->__toString(),
            ),
            $data,
        );
    }
}
