<?php

declare(strict_types=1);

namespace App\Reporting\TeamControl;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;

/**
 * What one collaborator did in a period, newest first: the evidence behind a row of
 * {@see TeamControlQuery}. A null user id is the bucket of records that predate author tracking.
 */
final readonly class TeamControlDetailQuery extends TeamControlSql
{
    private const int DETAIL_LIMIT = 50;

    /**
     * What one collaborator did in the period, newest first. A null user id is the "unattributed" bucket.
     *
     * @return list<array{type: string, at: string, reference: string|null, amount: float, note: string|null, saleId: string|null}>
     */
    public function detail(string $branchId, ?int $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $params = $this->params($branchId, $from, $to) + ['user' => $userId];
        $events = [];

        foreach ($this->rows(<<<'SQL'
            SELECT s.id, COALESCE(s.voided_at, s.updated_at) AS at, s.grand_total AS amount, s.void_reason AS note,
                   COALESCE(
                       (SELECT CONCAT(i.series, '-', i.number) FROM tax_document i
                        WHERE i.sale_id = s.id AND i.document_type IN ('01', '03')
                        ORDER BY i.created_at DESC LIMIT 1),
                       CONCAT(COALESCE(s.series, '-'), '-', COALESCE(s.number, '-'))
                   ) AS reference
            FROM sales s
            WHERE s.branch_id = :branch AND s.status = 'VOIDED'
              AND COALESCE(s.voided_at, s.updated_at) >= :from_ts AND COALESCE(s.voided_at, s.updated_at) < :to_ts
              AND s.voided_by_user_id <=> :user
            ORDER BY at DESC LIMIT 50
            SQL, $params) as $row) {
            $events[] = $this->event('sale_voided', $row);
        }

        foreach ($this->rows(<<<'SQL'
            SELECT td.sale_id AS id, td.created_at AS at, td.total AS amount, td.reason_description AS note,
                   CONCAT(td.series, '-', td.number) AS reference
            FROM tax_document td
            WHERE td.branch_id = :branch AND td.document_type = '07'
              AND td.created_at >= :from_ts AND td.created_at < :to_ts
              AND td.created_by_user_id <=> :user
            ORDER BY td.created_at DESC LIMIT 50
            SQL, $params) as $row) {
            $events[] = $this->event('credit_note', $row);
        }

        if ($userId !== null) {
            foreach ($this->rows(<<<'SQL'
                SELECT s.id, s.issued_at AS at, s.discount_total AS amount,
                       (SELECT MAX(sd.reason_text) FROM sale_discounts sd WHERE sd.sale_id = s.id) AS note,
                       COALESCE(
                           (SELECT CONCAT(i.series, '-', i.number) FROM tax_document i
                            WHERE i.sale_id = s.id AND i.document_type IN ('01', '03')
                            ORDER BY i.created_at DESC LIMIT 1),
                           CONCAT(COALESCE(s.series, '-'), '-', COALESCE(s.number, '-'))
                       ) AS reference
                FROM sales s
                WHERE s.branch_id = :branch AND s.status <> 'VOIDED' AND s.deleted_at IS NULL
                  AND s.issued_date BETWEEN :from_date AND :to_date
                  AND s.seller_user_id = :user AND s.discount_total > 0
                ORDER BY s.discount_total DESC LIMIT 50
                SQL, $params) as $row) {
                $events[] = $this->event('discount', $row);
            }

            foreach ($this->rows(<<<'SQL'
                SELECT cs.id, cs.closed_at AS at, cs.difference_amount AS amount, cs.close_note AS note,
                       CONCAT('Turno #', cs.id) AS reference
                FROM cash_sessions cs
                WHERE cs.branch_id = :branch AND cs.status = 'CLOSED'
                  AND cs.closed_at >= :from_ts AND cs.closed_at < :to_ts
                  AND cs.opened_by = :user
                  AND ABS(cs.difference_amount) >= :tolerance
                ORDER BY cs.closed_at DESC LIMIT 50
                SQL, $params) as $row) {
                $events[] = $this->event('cash_difference', $row);
            }

            foreach ($this->rows(
                <<<'SQL'
                    SELECT cm.id, COALESCE(cm.occurred_at, cm.created_at) AS at, cm.amount, cm.description AS note,
                           cat.name AS reference
                    FROM cash_movements cm
                    JOIN cash_sessions cs ON cs.id = cm.cash_session_id
                    JOIN cash_movement_category cat ON cat.id = cm.category_id
                    WHERE cs.branch_id = :branch AND cm.movement_type = 'CASH_OUT'
                      AND cm.sale_id IS NULL AND cm.return_id IS NULL
                      AND cat.code IN (:manual_codes)
                      AND COALESCE(cm.occurred_at, cm.created_at) >= :from_ts
                      AND COALESCE(cm.occurred_at, cm.created_at) < :to_ts
                      AND cm.created_by = :user
                    ORDER BY at DESC LIMIT 50
                    SQL,
                $params + ['manual_codes' => self::MANUAL_OUTFLOW_CODES],
                ['manual_codes' => ArrayParameterType::STRING],
            ) as $row) {
                $events[] = $this->event('manual_outflow', $row);
            }
        }

        usort($events, static fn(array $a, array $b): int => strcmp($b['at'], $a['at']));

        return array_slice($events, 0, self::DETAIL_LIMIT * 2);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{type: string, at: string, reference: string|null, amount: float, note: string|null, saleId: string|null}
     */
    private function event(string $type, array $row): array
    {
        return [
            'type' => $type,
            'at' => self::toBusinessTime((string) $row['at']),
            'reference' => $row['reference'] !== null ? (string) $row['reference'] : null,
            'amount' => (float) $row['amount'],
            'note' => $row['note'] !== null && trim((string) $row['note']) !== '' ? (string) $row['note'] : null,
            'saleId' => in_array($type, ['sale_voided', 'credit_note', 'discount'], true) && $row['id'] !== null
                ? (string) $row['id']
                : null,
        ];
    }
}
