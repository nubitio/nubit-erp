<?php

declare(strict_types=1);

namespace App\Settings\Repository;

use App\Settings\Entity\TenantProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TenantProfile> */
class TenantProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TenantProfile::class);
    }

    /**
     * Returns the singleton row, creating it if it does not exist yet.
     */
    public function getOrCreate(): TenantProfile
    {
        $rows = $this->findBy([], ['id' => 'ASC']);
        $profile = $rows[0] ?? null;

        if ($profile === null) {
            $profile = new TenantProfile();
            $this->getEntityManager()->persist($profile);
            $this->getEntityManager()->flush();

            return $profile;
        }

        return $profile;
    }
}
