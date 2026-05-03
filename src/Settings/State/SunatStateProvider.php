<?php

declare(strict_types=1);

namespace App\Settings\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Settings\Entity\SunatOperationalState;
use App\Settings\Repository\SunatOperationalStateRepository;

/** @implements ProviderInterface<SunatOperationalState> */
final readonly class SunatStateProvider implements ProviderInterface
{
    public function __construct(
        private SunatOperationalStateRepository $repository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SunatOperationalState
    {
        return $this->repository->getOrCreate();
    }
}
