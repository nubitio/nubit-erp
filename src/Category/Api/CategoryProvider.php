<?php

namespace App\Category\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Category\Domain\Category;
use App\Category\Domain\CategoryGetAllUseCase;
use App\Category\Domain\CategoryGetOneUseCase;
use App\Category\Infrastructure\CategoryEntity;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\JoinType;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;

/**
 * @implements ApiProviderInterface<Category>
 */
readonly class CategoryProvider implements ApiProviderInterface
{
    public function __construct(
        private CategoryGetAllUseCase $getAllUseCase,
        private CategoryGetOneUseCase $getOneUseCase,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.parent', 'p', JoinType::LEFT));
            $context = OptionUtils::addJoin($context, new JoinRule('e.imagen', 'i', JoinType::LEFT));
            $context = OptionUtils::addSort($context, new SortRule('descripcion', 'asc'));
            $options = OptionsBuilder::build($context, CategoryEntity::class, Category::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['categoria_id']));
    }
}
