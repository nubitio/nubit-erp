<?php

namespace App\RecipeDetail\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\FilterOperation;
use App\Core\Domain\DataLoader\FilterRule;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\JoinType;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\RecipeDetail\Domain\GetAllRecipeDetailUseCase;
use App\RecipeDetail\Domain\RecipeDetail;
use App\RecipeDetail\Infrastructure\RecipeDetailEntity;

/**
 * @implements ApiProviderInterface<RecipeDetail>
 */
readonly class RecipeDetailProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllRecipeDetailUseCase $getAllUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.material', 'm'));
            $context = OptionUtils::addJoin($context, new JoinRule('m.unidad', 'u', JoinType::LEFT));
            $context = OptionUtils::addFilter($context, new FilterRule('recipe_id', FilterOperation::Equals, $uriVariables['id']));
            $context = OptionUtils::addSort($context, new SortRule('id', 'asc'));
            $options = OptionsBuilder::build($context, RecipeDetailEntity::class, RecipeDetail::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return null;
    }
}
