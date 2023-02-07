<?php

namespace App\SaleDetail\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Infrastructure\DataLoader\FilterOperation;
use App\Core\Infrastructure\DataLoader\FilterRule;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\SaleDetail\Domain\GetAllSaleDetailUseCase;
use App\SaleDetail\Infrastructure\SaleDetailEntity;

readonly class SaleDetailProvider implements ProviderInterface
{
    public function __construct(
        private GetAllSaleDetailUseCase $getAllUseCase
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.producto', 'p'));
            //$context = OptionUtils::addSort($context, new SortRule('p.descripcion', 'asc'));
            $context = OptionUtils::addFilter($context, new FilterRule('movimiento', FilterOperation::Equals, $uriVariables['movimiento_id']));
            $options = OptionsBuilder::build($context, SaleDetailEntity::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return null;
    }
}