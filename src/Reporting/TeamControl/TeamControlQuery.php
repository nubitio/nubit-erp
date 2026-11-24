<?php

declare(strict_types=1);

namespace App\Reporting\TeamControl;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Read model behind "Control de caja y equipo": per-collaborator figures for one branch and period.
 *
 * It only reads. Every figure comes from data the operation already records (sales, tax documents,
 * cash sessions and cash movements); nothing here is a score, the rules that turn figures into
 * "review" signals live in {@see TeamControlRules}.
 *
 * Authors recorded before the author columns existed are NULL and are reported apart as
 * "unattributed" rather than guessed.
 *
 * @phpstan-type Metrics array{
 *     salesCount: int, salesTotal: float, subtotal: float, discountTotal: float,
 *     voidCount: int, voidTotal: float,
 *     creditNoteCount: int, creditNoteTotal: float,
 *     cashSessions: int, cashShortCount: int, cashShortage: float, cashSurplus: float,
 *     manualOutflowCount: int, manualOutflowTotal: float,
 * }
 */
final readonly class TeamControlQuery extends TeamControlSql
{
    /**
     * @return array{
     *     members: list<array{userId: int|null, name: string, metrics: Metrics}>,
     *     cashDifferences: list<array{sessionId: int, userId: int, name: string, closedAt: string, expected: float, counted: float, difference: float}>,
     * }
     */
    public function compute(string $branchId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $byUser = [];
        $params = $this->params($branchId, $from, $to);

        $this->accumulate(
            $byUser,
            <<<'SQL'
                SELECT s.seller_user_id AS user_id,
                       COUNT(*) AS sales_count,
                       COALESCE(SUM(s.grand_total), 0) AS sales_total,
                       COALESCE(SUM(s.subtotal), 0) AS subtotal,
                       COALESCE(SUM(s.discount_total), 0) AS discount_total
                FROM sales s
                WHERE s.branch_id = :branch
                  AND s.issued_date BETWEEN :from_date AND :to_date
                  AND s.status <> 'VOIDED'
                  AND s.deleted_at IS NULL
                  AND s.integration_client_id IS NULL
                GROUP BY s.seller_user_id
                SQL,
            $params,
            static function (array &$m, array $row): void {
                $m['salesCount'] = (int) $row['sales_count'];
                $m['salesTotal'] = (float) $row['sales_total'];
                $m['subtotal'] = (float) $row['subtotal'];
                $m['discountTotal'] = (float) $row['discount_total'];
            },
        );

        $this->accumulate(
            $byUser,
            <<<'SQL'
                SELECT s.voided_by_user_id AS user_id, COUNT(*) AS cnt, COALESCE(SUM(s.grand_total), 0) AS total
                FROM sales s
                WHERE s.branch_id = :branch
                  AND s.status = 'VOIDED'
                  AND COALESCE(s.voided_at, s.updated_at) >= :from_ts
                  AND COALESCE(s.voided_at, s.updated_at) < :to_ts
                GROUP BY s.voided_by_user_id
                SQL,
            $params,
            static function (array &$m, array $row): void {
                $m['voidCount'] = (int) $row['cnt'];
                $m['voidTotal'] = (float) $row['total'];
            },
        );

        $this->accumulate(
            $byUser,
            <<<'SQL'
                SELECT td.created_by_user_id AS user_id, COUNT(*) AS cnt, COALESCE(SUM(td.total), 0) AS total
                FROM tax_document td
                WHERE td.branch_id = :branch
                  AND td.document_type = '07'
                  AND td.created_at >= :from_ts
                  AND td.created_at < :to_ts
                GROUP BY td.created_by_user_id
                SQL,
            $params,
            static function (array &$m, array $row): void {
                $m['creditNoteCount'] = (int) $row['cnt'];
                $m['creditNoteTotal'] = (float) $row['total'];
            },
        );

        $this->accumulate(
            $byUser,
            <<<'SQL'
                SELECT cs.opened_by AS user_id,
                       COUNT(*) AS sessions,
                       COALESCE(SUM(cs.difference_amount <= -:tolerance), 0) AS short_count,
                       COALESCE(SUM(CASE WHEN cs.difference_amount <= -:tolerance THEN cs.difference_amount ELSE 0 END), 0) AS shortage,
                       COALESCE(SUM(CASE WHEN cs.difference_amount >= :tolerance THEN cs.difference_amount ELSE 0 END), 0) AS surplus
                FROM cash_sessions cs
                WHERE cs.branch_id = :branch
                  AND cs.status = 'CLOSED'
                  AND cs.closed_at >= :from_ts
                  AND cs.closed_at < :to_ts
                GROUP BY cs.opened_by
                SQL,
            $params,
            static function (array &$m, array $row): void {
                $m['cashSessions'] = (int) $row['sessions'];
                $m['cashShortCount'] = (int) $row['short_count'];
                $m['cashShortage'] = (float) $row['shortage'];
                $m['cashSurplus'] = (float) $row['surplus'];
            },
        );

        $this->accumulate(
            $byUser,
            <<<'SQL'
                SELECT cm.created_by AS user_id, COUNT(*) AS cnt, COALESCE(SUM(cm.amount), 0) AS total
                FROM cash_movements cm
                JOIN cash_sessions cs ON cs.id = cm.cash_session_id
                JOIN cash_movement_category cat ON cat.id = cm.category_id
                WHERE cs.branch_id = :branch
                  AND cm.movement_type = 'CASH_OUT'
                  AND cm.sale_id IS NULL
                  AND cm.return_id IS NULL
                  AND cat.code IN (:manual_codes)
                  AND COALESCE(cm.occurred_at, cm.created_at) >= :from_ts
                  AND COALESCE(cm.occurred_at, cm.created_at) < :to_ts
                GROUP BY cm.created_by
                SQL,
            $params + ['manual_codes' => self::MANUAL_OUTFLOW_CODES],
            static function (array &$m, array $row): void {
                $m['manualOutflowCount'] = (int) $row['cnt'];
                $m['manualOutflowTotal'] = (float) $row['total'];
            },
            ['manual_codes' => ArrayParameterType::STRING],
        );

        $cashDifferences = $this->cashDifferences($params);
        $ids = [];
        foreach (array_keys($byUser) as $key) {
            if ($key !== 'none') {
                $ids[] = $key;
            }
        }
        foreach ($cashDifferences as $difference) {
            $ids[] = $difference['userId'];
        }
        $names = $this->names($ids);

        $members = [];
        foreach ($byUser as $key => $metrics) {
            $userId = $key === 'none' ? null : (int) $key;
            $members[] = [
                'userId' => $userId,
                'name' => $userId === null ? 'Sin autor registrado' : $names[$userId] ?? 'Usuario ' . $userId,
                'metrics' => $metrics,
            ];
        }

        usort(
            $members,
            static fn(array $a, array $b): int => $b['metrics']['salesTotal'] <=> $a['metrics']['salesTotal'],
        );

        return [
            'members' => $members,
            'cashDifferences' => array_map(
                static fn(array $d): array => $d + ['name' => $names[$d['userId']] ?? 'Usuario ' . $d['userId']],
                $cashDifferences,
            ),
        ];
    }

    /**
     * Runs one metric query and writes each row into its collaborator's figures.
     *
     * @param array<int|string, Metrics>                                  $byUser
     * @param array<string, mixed>                                        $params
     * @param callable(Metrics, array<string, mixed>): void               $apply
     * @param array<string, ArrayParameterType>                           $types
     */
    private function accumulate(array &$byUser, string $sql, array $params, callable $apply, array $types = []): void
    {
        foreach ($this->rows($sql, $params, $types) as $row) {
            $metrics = &$this->slot($byUser, $row['user_id'] === null ? null : (int) $row['user_id']);
            $apply($metrics, $row);
            unset($metrics);
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<array{sessionId: int, userId: int, closedAt: string, expected: float, counted: float, difference: float}>
     */
    private function cashDifferences(array $params): array
    {
        $rows = $this->rows(<<<'SQL'
            SELECT cs.id, cs.opened_by AS user_id, cs.closed_at, cs.expected_amount, cs.counted_amount, cs.difference_amount
            FROM cash_sessions cs
            WHERE cs.branch_id = :branch AND cs.status = 'CLOSED'
              AND cs.closed_at >= :from_ts AND cs.closed_at < :to_ts
              AND ABS(cs.difference_amount) >= :tolerance
            ORDER BY cs.closed_at DESC LIMIT 5
            SQL, $params);

        return array_map(static fn(array $r): array => [
            'sessionId' => (int) $r['id'],
            'userId' => (int) $r['user_id'],
            'closedAt' => self::toBusinessTime((string) $r['closed_at']),
            'expected' => (float) $r['expected_amount'],
            'counted' => (float) $r['counted_amount'],
            'difference' => (float) $r['difference_amount'],
        ], $rows);
    }

    /**
     * @param list<int|string> $ids
     *
     * @return array<int, string>
     */
    private function names(array $ids): array
    {
        $ids = array_values(array_unique(array_map(intval(...), $ids)));
        if ($ids === []) {
            return [];
        }

        $names = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT usuario_id, username, display_name FROM usuario WHERE usuario_id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::INTEGER],
        ) as $row) {
            $display = trim((string) ($row['display_name'] ?? ''));
            $names[(int) $row['usuario_id']] = $display !== '' ? $display : (string) $row['username'];
        }

        return $names;
    }

    /**
     * @param array<int|string, Metrics> $byUser
     *
     * @return Metrics
     */
    private function &slot(array &$byUser, int|string|null $userId): array
    {
        $key = $userId === null ? 'none' : (string) (int) $userId;
        $byUser[$key] ??= [
            'salesCount' => 0,
            'salesTotal' => 0.0,
            'subtotal' => 0.0,
            'discountTotal' => 0.0,
            'voidCount' => 0,
            'voidTotal' => 0.0,
            'creditNoteCount' => 0,
            'creditNoteTotal' => 0.0,
            'cashSessions' => 0,
            'cashShortCount' => 0,
            'cashShortage' => 0.0,
            'cashSurplus' => 0.0,
            'manualOutflowCount' => 0,
            'manualOutflowTotal' => 0.0,
        ];

        return $byUser[$key];
    }
}
