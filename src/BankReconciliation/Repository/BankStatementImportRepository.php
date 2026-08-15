<?php

declare(strict_types=1);

namespace App\BankReconciliation\Repository;

use App\BankReconciliation\Entity\BankStatementImport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BankStatementImport>
 */
class BankStatementImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankStatementImport::class);
    }
}
