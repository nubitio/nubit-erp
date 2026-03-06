<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Assistant\RecentSaleDto;
use App\Dto\Assistant\SalesSummaryDto;
use App\Entity\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sale>
 */
class SaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sale::class);
    }

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }

    public function getSummary(\DateTimeImmutable $from, \DateTimeImmutable $to): SalesSummaryDto
    {
        $row = $this->getConnection()->fetchAssociative(
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
                'from' => $from->format('Y-m-d H:i:s'),
                'to' => $to->format('Y-m-d H:i:s'),
            ]
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
        $rows = $this->getConnection()->fetchAllAssociative(
            <<<'SQL'
SELECT
    s.id,
    s.grand_total                                            AS total,
    s.issued_at                                              AS fecha,
    CASE WHEN td.series IS NOT NULL AND td.number IS NOT NULL
         THEN CONCAT(td.series, '-', td.number)
         ELSE NULL
    END                                                      AS invoice,
    td.series                                                AS serie,
    td.number                                                AS numero
FROM sales s
LEFT JOIN tax_document td
       ON td.movimiento_id = s.id
      AND td.reason_code IS NULL
WHERE s.deleted_at IS NULL
ORDER BY s.issued_at DESC
LIMIT :limit
SQL,
            ['limit' => $limit],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER]
        );

        return array_map(static function (array $row): RecentSaleDto {
            $issuedAt = $row['fecha'] !== null
                ? (new \DateTimeImmutable((string) $row['fecha']))->format(\DateTimeInterface::ATOM)
                : (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

            return new RecentSaleDto(
                (string) $row['id'],
                (float) $row['total'],
                $issuedAt,
                isset($row['invoice']) ? (string) $row['invoice'] : null,
                isset($row['serie']) ? (string) $row['serie'] : null,
                isset($row['numero']) ? (string) $row['numero'] : null,
            );
        }, $rows);
    }
}
