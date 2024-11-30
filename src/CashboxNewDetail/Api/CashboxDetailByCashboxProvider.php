<?php

namespace App\CashboxNewDetail\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\CashboxNewDetail\Domain\CashboxDetail;
use App\CashboxNewDetail\Domain\GetAllCashboxDetailUseCase;
use App\CashboxNewDetail\Infrastructure\CashboxDetailEntity;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\FilterOperation;
use App\Core\Domain\DataLoader\FilterRule;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;

/**
 * @implements ApiProviderInterface<CashboxDetail>
 */
readonly class CashboxDetailByCashboxProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllCashboxDetailUseCase $getAllUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addFilter($context, new FilterRule('caja_id', FilterOperation::Equals, $uriVariables['caja_id']));
            $context = OptionUtils::addSort($context, new SortRule('id', 'asc'));
            $options = OptionsBuilder::build($context, CashboxDetailEntity::class, CashboxDetail::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return null;
    }
}
