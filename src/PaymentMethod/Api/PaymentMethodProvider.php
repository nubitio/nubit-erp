<?php

namespace App\PaymentMethod\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\PaymentMethod\Domain\GetAllPaymentMethodUseCase;
use App\PaymentMethod\Domain\GetOnePaymentMethodUseCase;
use App\PaymentMethod\Domain\PaymentMethod;
use App\PaymentMethod\Infrastructure\PaymentMethodEntity;

/**
 * @implements ApiProviderInterface<PaymentMethod>
 */
readonly class PaymentMethodProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllPaymentMethodUseCase $getAllUseCase,
        private GetOnePaymentMethodUseCase $getOneUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('name', 'asc'));
            $options = OptionsBuilder::build($context, PaymentMethodEntity::class, PaymentMethod::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}