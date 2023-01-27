<?php

namespace App\Category\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Category\Domain\CategoryGetAllUseCase;
use App\Category\Domain\CategoryGetOneUseCase;
use App\Category\Infrastructure\CategoryEntity;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;

readonly class CategoryProvider implements ProviderInterface
{
    public function __construct(
        private CategoryGetAllUseCase $getAllUseCase,
        private CategoryGetOneUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.parent', 'p'));
            $context = OptionUtils::addSort($context, new SortRule('descripcion', 'asc'));
            $options = OptionsBuilder::build($context, CategoryEntity::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['categoria_id']);
    }
}