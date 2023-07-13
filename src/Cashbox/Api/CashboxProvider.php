<?php

namespace App\Cashbox\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Cashbox\Domain\Cashbox;
use App\Cashbox\Domain\GetAllCashboxUseCase;
use App\Cashbox\Domain\GetOneCashboxUseCase;
use App\Cashbox\Infrastructure\CashboxEntity;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;

/**
 * @implements ApiProviderInterface<Cashbox>
 */
readonly class CashboxProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllCashboxUseCase $getAllUseCase,
        private GetOneCashboxUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.local', 'l'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.usuario', 'u'));
            $context = OptionUtils::addSort($context, new SortRule('apertura', 'desc'));
            $options = OptionsBuilder::build($context, CashboxEntity::class, Cashbox::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['caja_id']));
    }
}
