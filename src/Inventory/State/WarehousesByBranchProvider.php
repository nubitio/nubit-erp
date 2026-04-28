<?php

declare(strict_types=1);

namespace App\Inventory\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Inventory\Entity\Warehouse;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProviderInterface<object>
 */
readonly class WarehousesByBranchProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FeatureCheckerInterface $featureChecker,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
        $this->featureChecker->requireFeature('multi_warehouse');

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
