<?php

namespace App\Sale\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\FilterOperation;
use App\Core\Infrastructure\DataLoader\FilterRule;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Sale\Domain\GetAllSaleUseCase;
use App\Sale\Domain\GetOneSaleUseCase;
use App\Sale\Domain\Sale;
use App\Sale\Infrastructure\SaleEntity;

/**
 * @implements ApiProviderInterface<Sale>
 */
readonly class SaleProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllSaleUseCase $getAllUseCase,
        private GetOneSaleUseCase $getOneUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.cliente', 'c'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.almacen', 'a'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.tipo_pago', 'pm'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.details', 'sd'));
            $context = OptionUtils::addSort($context, new SortRule('date_created', 'desc'));
            $context = OptionUtils::addFilter($context, new FilterRule('numero', FilterOperation::GreaterThan, 0));
            $context = OptionUtils::addFilter($context, new FilterRule('tipo_id', FilterOperation::Equals, 2));
            $options = OptionsBuilder::build($context, SaleEntity::class, Sale::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['movimiento_id']);
    }
}