<?php

declare(strict_types=1);

namespace App\Sales\Report\Summary;

use App\Efact\Domain\Money;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;

final readonly class MonthlySalesSummary
{
    public function __construct(
        private Connection $connection,
    ) {}

    /** @return array{periodStart: string, periodEnd: string, currencies: list<array{currency: string, sold: string, balanceDue: string, saleCount: int, sunatPending: int}>} */
    public function get(string $branchId, ?DateTimeImmutable $now = null): array
    {
        $today = ($now ?? new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->setTimezone(
            new DateTimeZone('America/Lima'),
        );
        $start = $today->modify('first day of this month')->format('Y-m-d');
        $end = $today->modify('first day of next month')->format('Y-m-d');
        // DBAL bypasses Doctrine filters: scope both branch and deletion explicitly.
        // Keep currencies separate: there is no implicit currency conversion.
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT currency, SUM(grand_total) AS sold, SUM(balance_due) AS balance_due,
                       COUNT(*) AS sale_count,
                       SUM(CASE WHEN EXISTS (
                         SELECT 1 FROM tax_document td
                         WHERE (td.sale_id = s.id OR td.movimiento_id = s.id)
                           AND td.reason_code IS NULL
                           AND (td.sunat_status IS NULL OR td.sunat_status <> '0')
                           AND NOT EXISTS (
                             SELECT 1 FROM tax_document newer
                             WHERE (newer.sale_id = s.id OR newer.movimiento_id = s.id)
                               AND newer.reason_code IS NULL
                               AND (newer.created_at > td.created_at OR
                                   (newer.created_at = td.created_at AND newer.tax_document_id > td.tax_document_id))
                           )
                       ) THEN 1 ELSE 0 END) AS sunat_pending
                FROM sales s
                WHERE branch_id = :branch AND deleted_at IS NULL AND status <> 'VOIDED'
                  AND issued_date >= :start AND issued_date < :end
                GROUP BY currency ORDER BY currency
                SQL,
            ['branch' => $branchId, 'start' => $start, 'end' => $end],
        );

        return [
            'periodStart' => $start,
            'periodEnd' => $end,
            'currencies' => array_map(static fn(array $row): array => [
                'currency' => $row['currency'],
                'sold' => Money::toString(Money::of($row['sold'])),
                'balanceDue' => Money::toString(Money::of($row['balance_due'])),
                'saleCount' => (int) $row['sale_count'],
                'sunatPending' => (int) $row['sunat_pending'],
            ], $rows),
        ];
    }
}
