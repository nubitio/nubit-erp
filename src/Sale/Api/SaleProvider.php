<?php

namespace App\Sale\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Infrastructure\DataLoader\Filter;
use App\Core\Infrastructure\DataLoader\FilterOperation;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\Sort;
use App\Sale\Domain\GetAllSaleUseCase;
use App\Sale\Domain\GetOneSaleUseCase;
use App\Sale\Infrastructure\SaleEntity;

readonly class SaleProvider implements ProviderInterface
{
    public function __construct(
        private GetAllSaleUseCase $getAllUseCase,
        private GetOneSaleUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new Sort('date_created', 'desc'));
            $context = OptionUtils::addFilter($context, new Filter('numero', FilterOperation::GreaterThan, 0));
            $context = OptionUtils::addFilter($context, new Filter('tipo_id', FilterOperation::Equals, 2));
            $context = OptionUtils::addJoin($context, new JoinRule('e.cliente', 'c'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.tipo_pago', 'pm'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.details', 'sd'));
            $options = OptionsBuilder::build($context, SaleEntity::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['movimiento_id']);
    }
}