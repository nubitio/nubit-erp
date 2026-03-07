<?php

declare(strict_types=1);

namespace App\State\CashRegister;

use Override;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * @implements ProviderInterface<object>
 */
readonly class CashRegisterShiftsProvider implements ProviderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @inheritdoc
     * @throws Exception|DateMalformedStringException
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $cashRegisterId = (int) ($uriVariables['id'] ?? 0);

        $filters = $context['filters'] ?? [];
        $itemsPerPage = isset($filters['itemsPerPage']) ? (int) $filters['itemsPerPage'] : 20;
        $page = isset($filters['page']) ? (int) $filters['page'] : 1;

        // DevExtreme may send skip/take instead of page/itemsPerPage.
        if (isset($filters['take'])) {
            $itemsPerPage = (int) $filters['take'];
        }

        if (isset($filters['skip']) && $itemsPerPage > 0) {
            $page = (int) floor(((int) $filters['skip']) / $itemsPerPage) + 1;
        }

        $offset = max(0, ($page - 1) * $itemsPerPage);

        // Count total sessions for this cash register
        $total = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM cash_sessions WHERE cash_register_id = ?',
            [$cashRegisterId]
        );

        // Fetch paginated sessions with cashier username
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                cs.id,
                cs.status,
                cs.opening_amount,
                cs.closing_amount,
                cs.opened_at,
                cs.closed_at,
                cs.note,
                u.username AS cashier_username,
                COALESCE(SUM(CASE WHEN cm.movement_type = 'CASH_IN'  THEN cm.amount ELSE 0 END), 0) AS cash_in_total,
                COALESCE(SUM(CASE WHEN cm.movement_type = 'CASH_OUT' THEN cm.amount ELSE 0 END), 0) AS cash_out_total,
                COALESCE(SUM(CASE WHEN cm.movement_type = 'SALE_IN'  THEN cm.amount ELSE 0 END), 0) AS sale_in_total
            FROM cash_sessions cs
            LEFT JOIN usuario u ON u.usuario_id = cs.cashier_id
            LEFT JOIN cash_movements cm ON cm.cash_session_id = cs.id
            WHERE cs.cash_register_id = ?
            GROUP BY cs.id
            ORDER BY cs.opened_at DESC, cs.id DESC
            LIMIT ? OFFSET ?
            SQL,
            [$cashRegisterId, $itemsPerPage, $offset]
        );

        $items = array_map(
            static function (array $row): array {
                $opening = BigDecimal::of((string) $row['opening_amount']);
                $cashIn  = BigDecimal::of((string) $row['cash_in_total']);
                $cashOut = BigDecimal::of((string) $row['cash_out_total']);
                $saleIn  = BigDecimal::of((string) $row['sale_in_total']);

                // closingBalance = opening + CASH_IN - CASH_OUT + SALE_IN
                $closingBalance = $opening->plus($cashIn)->minus($cashOut)->plus($saleIn);

                return [
                    'id'              => (int) $row['id'],
                    'status'          => strtolower((string) $row['status']),
                    'openingBalance'  => $opening->toScale(2, RoundingMode::HALF_UP)->__toString(),
                    'closingBalance'  => $row['closing_amount'] !== null
                        ? BigDecimal::of((string) $row['closing_amount'])
                            ->toScale(2, RoundingMode::HALF_UP)->__toString()
                        : $closingBalance->toScale(2, RoundingMode::HALF_UP)->__toString(),
                    'expectedAmount'  => $closingBalance->toScale(2, RoundingMode::HALF_UP)->__toString(),
                    'countedAmount'   => $row['closing_amount'] !== null
                        ? BigDecimal::of((string) $row['closing_amount'])
                            ->toScale(2, RoundingMode::HALF_UP)->__toString()
                        : null,
                    'difference'      => $row['closing_amount'] !== null
                        ? BigDecimal::of((string) $row['closing_amount'])
                            ->minus($closingBalance)
                            ->toScale(2, RoundingMode::HALF_UP)->__toString()
                        : null,
                    'note'            => $row['note'],
                    'openedAt'        => $row['opened_at'] ? new DateTimeImmutable($row['opened_at']) : null,
                    'closedAt'        => $row['closed_at'] ? new DateTimeImmutable($row['closed_at']) : null,
                    'cashier'         => $row['cashier_username'],
                ];
            },
            $rows
        );

        return new TraversablePaginator(
            new \ArrayIterator($items),
            (float) $page,
            (float) $itemsPerPage,
            (float) $total,
        );
    }
}
