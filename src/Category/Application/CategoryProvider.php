<?php

namespace App\Category\Application;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Category\Domain\CategoryGetAllUseCase;
use App\Category\Domain\CategoryGetOneUseCase;
use App\Category\Infrastructure\CategoryEntity;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;

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
            $sortRules = $context['filters']['order'] ?? [];
            $sortRules = is_array($sortRules) ? $sortRules : [$sortRules];

            $filterRules = $context['filters']['filterRules'] ?? "[]";
            $filterRules = json_decode($filterRules, true);

            $options = new Options(
                entity: CategoryEntity::class,
                page: $context['filters']['page'] ?? 1,
                rows: $context['filters']['rows'] ?? 10,
                sort: OptionsBuilder::buildSort($sortRules),
                filters: OptionsBuilder::buildFilters($filterRules),
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