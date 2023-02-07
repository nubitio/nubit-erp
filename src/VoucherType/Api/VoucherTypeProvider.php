<?php

namespace App\VoucherType\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\VoucherType\Domain\GetAllVoucherTypeUseCase;
use App\VoucherType\Domain\GetOneVoucherTypeUseCase;
use App\VoucherType\Infrastructure\VoucherTypeEntity;

readonly class VoucherTypeProvider implements ProviderInterface
{
    public function __construct(
        private GetAllVoucherTypeUseCase $getAllUseCase,
        private GetOneVoucherTypeUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('descripcion', 'asc'));
            $options = OptionsBuilder::build($context, VoucherTypeEntity::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['id']);
    }
}