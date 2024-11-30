<?php

namespace App\SaleDetail\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\FilterOperation;
use App\Core\Domain\DataLoader\FilterRule;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\SaleDetail\Domain\GetAllSaleDetailUseCase;
use App\SaleDetail\Domain\SaleDetail;
use App\SaleDetail\Infrastructure\SaleDetailEntity;

/**
 * @implements ApiProviderInterface<SaleDetail>
 */
readonly class SaleDetailProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllSaleDetailUseCase $getAllUseCase,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.producto', 'p'));
            // $context = OptionUtils::addSort($context, new SortRule('p.descripcion', 'asc'));
            $context = OptionUtils::addFilter($context, new FilterRule('movimiento', FilterOperation::Equals, strval($uriVariables['movimiento_id'])));
            $options = OptionsBuilder::build($context, SaleDetailEntity::class, SaleDetail::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return null;
    }
}
