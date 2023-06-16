<?php

namespace App\Production\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\JoinType;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Production\Domain\GetAllProductionUseCase;
use App\Production\Domain\GetOneProductionUseCase;
use App\Production\Domain\Production;
use App\Production\Infrastructure\ProductionEntity;

/**
 * @implements ApiProviderInterface<Production>
 */
readonly class ProductionProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllProductionUseCase $getAllUseCase,
        private GetOneProductionUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.product', 'p'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.recipe', 'r', JoinType::LEFT));
            $context = OptionUtils::addJoin($context, new JoinRule('e.employee', 'y'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.production_line', 'pl'));
            $context = OptionUtils::addSort($context, new SortRule('id', 'asc'));
            $options = OptionsBuilder::build($context, ProductionEntity::class, Production::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}
