<?php

namespace App\Product\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\Sort;
use App\Product\Domain\GetAllProductUseCase;
use App\Product\Domain\GetOneProductUseCase;
use App\Product\Infrastructure\ProductEntity;

readonly class ProductProvider implements ProviderInterface
{
    public function __construct(
        private GetAllProductUseCase $getAllUseCase,
        private GetOneProductUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new Sort('descripcion', 'asc'));
            $options = OptionsBuilder::build($context, ProductEntity::class);
            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['producto_id']);
    }
}