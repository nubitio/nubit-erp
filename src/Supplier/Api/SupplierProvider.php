<?php

namespace App\Supplier\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\Supplier\Domain\GetAllSupplierUseCase;
use App\Supplier\Domain\GetOneSupplierUseCase;
use App\Supplier\Domain\Supplier;
use App\Supplier\Infrastructure\SupplierEntity;

/**
 * @implements ApiProviderInterface<Supplier>
 */
readonly class SupplierProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllSupplierUseCase $getAllUseCase,
        private GetOneSupplierUseCase $getOneUseCase,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('razon_social', 'asc'));
            $options = OptionsBuilder::build($context, SupplierEntity::class, Supplier::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['proveedor_id']));
    }
}
