<?php

namespace App\ProductionDetail\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\FilterOperation;
use App\Core\Domain\DataLoader\FilterRule;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\ProductionDetail\Domain\GetAllProductionDetailUseCase;
use App\ProductionDetail\Domain\ProductionDetail;
use App\ProductionDetail\Infrastructure\ProductionDetailEntity;

/**
 * @implements ApiProviderInterface<ProductionDetail>
 */
readonly class ProductionDetailProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllProductionDetailUseCase $getAllUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addFilter($context, new FilterRule('production_id', FilterOperation::Equals, $uriVariables['id']));
            $context = OptionUtils::addJoin($context, new JoinRule('e.material', 'm'));
            $context = OptionUtils::addSort($context, new SortRule('id', 'asc'));
            $options = OptionsBuilder::build($context, ProductionDetailEntity::class, ProductionDetail::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return null;
    }
}
