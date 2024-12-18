<?php

namespace App\CashboxDetail\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\CashboxDetail\Domain\CashboxDetail;
use App\CashboxDetail\Domain\GetAllCashboxDetailUseCase;
use App\CashboxDetail\Domain\GetOneCashboxDetailUseCase;
use App\CashboxDetail\Infrastructure\CashboxDetailEntity;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;

/**
 * @implements ApiProviderInterface<CashboxDetail>
 */
readonly class CashboxDetailProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllCashboxDetailUseCase $getAllUseCase,
        private GetOneCashboxDetailUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('id', 'asc'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.category', 'c'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.employee', 'emp'));
            $options = OptionsBuilder::build($context, CashboxDetailEntity::class, CashboxDetail::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}
