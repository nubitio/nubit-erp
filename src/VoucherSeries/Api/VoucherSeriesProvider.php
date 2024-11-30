<?php

namespace App\VoucherSeries\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\VoucherSeries\Domain\GetAllVoucherSeriesUseCase;
use App\VoucherSeries\Domain\GetOneVoucherSeriesUseCase;
use App\VoucherSeries\Domain\VoucherSeries;
use App\VoucherSeries\Infrastructure\VoucherSeriesEntity;

/**
 * @implements ApiProviderInterface<VoucherSeries>
 */
readonly class VoucherSeriesProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllVoucherSeriesUseCase $getAllUseCase,
        private GetOneVoucherSeriesUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.local', 'l'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.tipo_comprobante', 'tc'));
            $context = OptionUtils::addSort($context, new SortRule('id', 'asc'));
            $options = OptionsBuilder::build($context, VoucherSeriesEntity::class, VoucherSeries::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}
