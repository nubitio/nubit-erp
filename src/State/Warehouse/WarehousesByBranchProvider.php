<?php

declare(strict_types=1);

namespace App\State\Warehouse;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Warehouse;
use Doctrine\ORM\EntityManagerInterface;

readonly class WarehousesByBranchProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
        $branchId = $uriVariables['id'];

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('w.id', 'w.name');
        $qb->from(Warehouse::class, 'w');
        $qb->where('w.branch = :branchId');
        $qb->setParameter('branchId', $branchId);
        $qb->orderBy('w.name');

        return $qb->getQuery()->getArrayResult();
    }
}
