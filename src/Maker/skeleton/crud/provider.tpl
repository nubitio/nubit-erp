<?php

namespace App\{{module}}\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\{{module}}\Infrastructure\{{module}}Entity;
use App\{{module}}\Domain\{{module}}GetAllUseCase;
use App\{{module}}\Domain\{{module}}GetOneUseCase;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;

readonly class {{module}}Provider implements ProviderInterface
{
    public function __construct(
        private {{module}}GetAllUseCase $getAllUseCase,
        private {{module}}GetOneUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $options = OptionsBuilder::build($context, {{module}}Entity::class);
            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['{{entityId}}']);
    }
}