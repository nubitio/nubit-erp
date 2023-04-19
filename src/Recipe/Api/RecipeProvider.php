<?php

namespace App\Recipe\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Recipe\Domain\Recipe;
use App\Recipe\Domain\GetAllRecipeUseCase;
use App\Recipe\Domain\GetOneRecipeUseCase;
use App\Recipe\Infrastructure\RecipeEntity;

/**
 * @implements ApiProviderInterface<Recipe>
 */
readonly class RecipeProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllRecipeUseCase $getAllUseCase,
        private GetOneRecipeUseCase $getOneUseCase,
    )
    {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('name', 'asc'));
            $options = OptionsBuilder::build($context, RecipeEntity::class, Recipe::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}