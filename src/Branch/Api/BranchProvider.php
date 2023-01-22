<?php

namespace App\Branch\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Branch\Infrastructure\BranchEntity;
use App\Branch\Domain\BranchGetAllUseCase;
use App\Branch\Domain\BranchGetOneUseCase;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;

readonly class BranchProvider implements ProviderInterface
{
    public function __construct(
        private BranchGetAllUseCase $getAllUseCase,
        private BranchGetOneUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $options = OptionsBuilder::build($context, BranchEntity::class);
            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['local_id']);
    }
}