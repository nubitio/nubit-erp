<?php

namespace App\CashboxDetail\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\CashboxDetail\Domain\CashboxDetail;
use App\CashboxDetail\Domain\GetAllCashboxDetailUseCase;
use App\CashboxDetail\Infrastructure\CashboxDetailEntity;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\FilterOperation;
use App\Core\Domain\DataLoader\FilterRule;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\JoinType;
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
            $context = OptionUtils::addJoin($context, new JoinRule('e.category', 'c', JoinType::LEFT));
            $context = OptionUtils::addJoin($context, new JoinRule('e.employee', 'emp', JoinType::LEFT));
            $context = OptionUtils::addFilter(
                $context,
                new FilterRule('caja_id', FilterOperation::Equals, $uriVariables['caja_id'])
            );
            $context = OptionUtils::addSort($context, new SortRule('id', 'asc'));
            $options = OptionsBuilder::build($context, CashboxDetailEntity::class, CashboxDetail::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return null;
    }
}
