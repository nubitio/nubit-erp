<?php

declare(strict_types=1);

namespace App\Settings\Repository;

use App\Settings\Entity\TenantSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TenantSettings> */
class TenantSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TenantSettings::class);
    }

    /**
     * Returns the singleton row, creating it with defaults if it does not exist yet.
     */
    public function getOrCreate(): TenantSettings
    {
        $settings = $this->findOneBy([]);

        if ($settings === null) {
            $settings = new TenantSettings();
            $this->getEntityManager()->persist($settings);
            $this->getEntityManager()->flush();
        }

        return $settings;
    }
}
