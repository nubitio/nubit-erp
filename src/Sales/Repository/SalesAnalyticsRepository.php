<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use App\AI\Dto\RecentSaleDto;
use App\AI\Dto\SalesSummaryDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * DBAL-only read repository for the V2 sales analytics.
 *
 * This service replaced the legacy SaleRepository (which extended
 * ServiceEntityRepository<Sale> and was bound to the movimiento ORM entity).
 * All queries here operate exclusively on the V2 `sales` table.
 */
final readonly class SalesAnalyticsRepository
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function getSummary(\DateTimeImmutable $from, \DateTimeImmutable $to): SalesSummaryDto
    {
        // $from/$to are Lima calendar dates; expand to UTC-equivalent boundaries so
        // sales between 19:00–23:59 Lima time (00:00–04:59 UTC next day) are included.
        $lima = new \DateTimeZone('America/Lima');
        $utc = new \DateTimeZone('UTC');
        $fromUtc = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $from->format('Y-m-d') . ' 00:00:00',
            $lima,
        )->setTimezone($utc);
        $toUtc = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $to->format('Y-m-d') . ' 23:59:59',
            $lima,
        )->setTimezone($utc);

        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                    COUNT(*)                            AS cnt,
                    COALESCE(SUM(s.grand_total), 0)     AS totalAmount,
                    COALESCE(AVG(s.grand_total), 0)     AS avgAmount
                FROM sales s
                WHERE s.issued_at BETWEEN :from AND :to
                  AND s.deleted_at IS NULL
                SQL,
            [
                'from' => $fromUtc->format('Y-m-d H:i:s'),
                'to' => $toUtc->format('Y-m-d H:i:s'),
            ],
        );

        if ($row === false) {
            $row = ['cnt' => 0, 'totalAmount' => 0, 'avgAmount' => 0];
        }

        return new SalesSummaryDto(
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            (int) ($row['cnt'] ?? 0),
            (float) ($row['totalAmount'] ?? 0),
            (float) ($row['avgAmount'] ?? 0),
        );
    }

    /**
     * @return RecentSaleDto[]
     */
    public function getRecentSales(int $limit = 5): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT
                s.id,
                s.grand_total                                            AS total,
                s.issued_at                                              AS fecha,
                CASE WHEN td.series IS NOT NULL AND td.number IS NOT NULL
                     THEN CONCAT(td.series, '-', td.number)
                     ELSE NULL
                END                                                      AS invoice,
                td.series                                                AS series,
                td.number                                                AS number
            FROM sales s
            LEFT JOIN tax_document td
                   ON td.movimiento_id = s.id
                  AND td.reason_code IS NULL
            WHERE s.deleted_at IS NULL
            ORDER BY s.issued_at DESC
            LIMIT :limit
            SQL, ['limit' => $limit], ['limit' => ParameterType::INTEGER]);

        return array_map(static function (array $row): RecentSaleDto {
            $issuedAt = $row['fecha'] !== null
                ? new \DateTimeImmutable((string) $row['fecha'])->format(\DateTimeInterface::ATOM)
                : new \DateTimeImmutable()->format(\DateTimeInterface::ATOM);

            return new RecentSaleDto(
                (string) $row['id'],
                (float) $row['total'],
                $issuedAt,
                isset($row['invoice']) ? (string) $row['invoice'] : null,
                isset($row['series']) ? (string) $row['series'] : null,
                isset($row['number']) ? (string) $row['number'] : null,
            );
        }, $rows);
    }
}
