<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use App\Inventory\Entity\LogisticOperation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LogisticOperation>
 */
class LogisticOperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LogisticOperation::class);
    }
}
