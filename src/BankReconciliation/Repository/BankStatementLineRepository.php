<?php

declare(strict_types=1);

namespace App\BankReconciliation\Repository;

use App\BankReconciliation\Entity\BankStatementImport;
use App\BankReconciliation\Entity\BankStatementLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BankStatementLine>
 */
class BankStatementLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankStatementLine::class);
    }

    /** @return list<BankStatementLine> */
    public function findUnmatchedByImportBatch(BankStatementImport $importBatch): array
    {
        return $this
            ->createQueryBuilder('l')
            ->andWhere('l.importBatch = :importBatch')
            ->andWhere('l.status = :status')
            ->setParameter('importBatch', $importBatch)
            ->setParameter('status', BankStatementLine::UNMATCHED)
            ->getQuery()
            ->getResult();
    }
}
