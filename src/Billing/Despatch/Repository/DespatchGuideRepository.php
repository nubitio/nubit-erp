<?php

declare(strict_types=1);

namespace App\Billing\Despatch\Repository;

use App\Billing\Despatch\Entity\DespatchGuide;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DespatchGuide>
 */
class DespatchGuideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DespatchGuide::class);
    }

    /**
     * Devuelve el siguiente correlativo para una serie dada, bajo bloqueo
     * pesimista para evitar números duplicados ante emisión concurrente.
     */
    public function allocateNextNumber(string $series): int
    {
        $em = $this->getEntityManager();

        return (int) $em->wrapInTransaction(function () use ($em, $series): int {
            $max = $em->getConnection()->fetchOne('SELECT MAX(CAST(number AS UNSIGNED)) FROM despatch_guide WHERE series = ? FOR UPDATE', [
                $series,
            ]);

            return (int) $max + 1;
        });
    }
}
