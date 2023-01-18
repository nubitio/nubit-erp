<?php

namespace App\Category\Application;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Category\Domain\CategoryGetAllUseCase;
use App\Category\Domain\CategoryGetOneUseCase;
use App\Category\Infrastructure\CategoryEntity;
use App\Core\Infrastructure\Common\DataLoader\Options;

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
            $options = new Options(
                entity: CategoryEntity::class,
                page: $context['filters']['page'] ?? 1,
                rows: $context['filters']['rows'] ?? 10,
                //sort: OptionsBuilder::buildSort($context['filters']['sort'] ?? []),
                //filters: OptionsBuilder::buildFilters($context['filters']['filters'] ?? []),
                sort: [],
                filters: [],
            );
            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(
            [
                'categoria_id' => $uriVariables['categoria_id'],
            ]
        );
    }
}