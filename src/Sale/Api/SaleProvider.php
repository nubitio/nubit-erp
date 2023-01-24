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
use App\Customer\Infrastructure\CustomerEntity;
use App\PaymentMethod\Infrastructure\PaymentMethodEntity;
use App\Sale\Domain\GetAllSaleUseCase;
use App\Sale\Domain\GetOneSaleUseCase;
use App\Sale\Infrastructure\SaleEntity;
use App\SaleDetail\Infrastructure\SaleDetailEntity;

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
            $context = OptionUtils::addJoin($context, new JoinRule(CustomerEntity::class, 'c', 'inner', 'e.cliente_id = c.id'));
            $context = OptionUtils::addJoin($context, new JoinRule(PaymentMethodEntity::class, 'pm', 'inner', 'e.tipo_pago = pm.id'));
            $context = OptionUtils::addJoin($context, new JoinRule(SaleDetailEntity::class, 'sd', 'inner', 'e.movimiento_id = sd.movimiento_id'));
            $options = OptionsBuilder::build($context, SaleEntity::class);
            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['movimiento_id']);
    }
}