<?php

namespace App\Recipe\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\JoinType;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\Recipe\Domain\GetAllRecipeUseCase;
use App\Recipe\Domain\GetOneRecipeUseCase;
use App\Recipe\Domain\Recipe;
use App\Recipe\Infrastructure\RecipeEntity;

/**
 * @implements ApiProviderInterface<Recipe>
 */
readonly class RecipeProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllRecipeUseCase $getAllUseCase,
        private GetOneRecipeUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.product', 'p'));
            $context = OptionUtils::addJoin($context, new JoinRule('p.unidad', 'u', JoinType::LEFT));
            $context = OptionUtils::addSort($context, new SortRule('id', 'asc'));
            $options = OptionsBuilder::build($context, RecipeEntity::class, Recipe::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}
