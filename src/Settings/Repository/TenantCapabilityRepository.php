<?php

declare(strict_types=1);

namespace App\Settings\Repository;

use App\Settings\Entity\TenantCapability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TenantCapability> */
class TenantCapabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TenantCapability::class);
    }

    public function findByKey(string $capabilityKey): ?TenantCapability
    {
        return $this->findOneBy(['capabilityKey' => $capabilityKey]);
    }

    /**
     * @return array<string, TenantCapability> keyed by capability key
     */
    public function allByKey(): array
    {
        $rows = [];
        foreach ($this->findAll() as $row) {
            $rows[$row->getCapabilityKey()] = $row;
        }

        return $rows;
    }
}
