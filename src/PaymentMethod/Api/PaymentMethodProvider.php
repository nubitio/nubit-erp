<?php

namespace App\PaymentMethod\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\PaymentMethod\Domain\GetAllPaymentMethodUseCase;
use App\PaymentMethod\Domain\GetOnePaymentMethodUseCase;
use App\PaymentMethod\Infrastructure\PaymentMethodEntity;

readonly class PaymentMethodProvider implements ProviderInterface
{
    public function __construct(
        private GetAllPaymentMethodUseCase $getAllUseCase,
        private GetOnePaymentMethodUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('name', 'asc'));
            $options = OptionsBuilder::build($context, PaymentMethodEntity::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['id']);
    }
}