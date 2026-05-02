<?php

declare(strict_types=1);

namespace App\Inventory\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Enforces the multi_warehouse feature gate only when a tenant tries to
 * create a SECOND warehouse for the same branch. A single warehouse is
 * always allowed regardless of plan.
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class WarehouseCreateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $inner
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $inner,
        private EntityManagerInterface $entityManager,
        private FeatureCheckerInterface $featureChecker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $branch = $data instanceof Warehouse ? $data->getBranch() : null;

        if ($branch instanceof Branch) {
            $existing = $this->entityManager
                ->getRepository(Warehouse::class)
                ->count(['branch' => $branch]);

            if ($existing >= 1) {
                // Already has a warehouse — require multi_warehouse to add more
                $this->featureChecker->requireFeature('multi_warehouse');
            }
        }

        return $this->inner->process($data, $operation, $uriVariables, $context);
    }
}
