<?php

namespace App\Category\Application;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Category\Domain\CategoryGetAllUseCase;
use App\Category\Domain\CategoryGetOneUseCase;

class CategoryProvider implements ProviderInterface
{
    public function __construct(
        private readonly CategoryGetAllUseCase $getAllUseCase,
        private readonly CategoryGetOneUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->getAllUseCase->__invoke($context['filters'] ?? null);
        }

        return $this->getOneUseCase->__invoke(
            [
                'categoria_id' => $uriVariables['categoria_id'],
            ]
        );
    }
}