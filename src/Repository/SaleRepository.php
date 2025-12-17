<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Sale;
use App\Dto\Assistant\RecentSaleDto;
use App\Dto\Assistant\SalesSummaryDto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    public function getSummary(\DateTimeImmutable $from, \DateTimeImmutable $to): SalesSummaryDto
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id) AS cnt')
            ->addSelect('COALESCE(SUM(s.total), 0) AS totalAmount')
            ->addSelect('COALESCE(AVG(s.total), 0) AS avgAmount')
            ->where('s.fecha BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        $result = $qb->getQuery()->getSingleResult();

        return new SalesSummaryDto(
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            (int) ($result['cnt'] ?? 0),
            (float) ($result['totalAmount'] ?? 0),
            (float) ($result['avgAmount'] ?? 0),
        );
    }

    /**
     * @return RecentSaleDto[]
     */
    public function getRecentSales(int $limit = 5): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.id', 's.total', 's.fecha', 's.invoice', 's.serie', 's.numero')
            ->orderBy('s.fecha', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $row): RecentSaleDto {
            $issuedAt = $row['fecha'] instanceof \DateTimeInterface
                ? $row['fecha']->format(\DateTimeInterface::ATOM)
                : (string) $row['fecha'];

            return new RecentSaleDto(
                (string) $row['id'],
                (float) $row['total'],
                $issuedAt,
                $row['invoice'],
                $row['serie'] ?? null,
                $row['numero'] ?? null,
            );
        }, $rows);
    }
}
