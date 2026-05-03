<?php

declare(strict_types=1);

namespace App\Settings\Repository;

use App\Settings\Entity\SunatOperationalState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SunatOperationalState> */
class SunatOperationalStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SunatOperationalState::class);
    }

    public function getOrCreate(): SunatOperationalState
    {
        $state = $this->findOneBy([]);

        if ($state === null) {
            $state = new SunatOperationalState();
            $this->getEntityManager()->persist($state);
            $this->getEntityManager()->flush();
        }

        return $state;
    }
}
