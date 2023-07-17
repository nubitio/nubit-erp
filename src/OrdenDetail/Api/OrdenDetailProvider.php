<?php

namespace App\OrdenDetail\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\FilterOperation;
use App\Core\Infrastructure\DataLoader\FilterRule;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\OrdenDetail\Domain\GetAllOrdenDetailUseCase;
use App\OrdenDetail\Domain\OrdenDetail;
use App\OrdenDetail\Infrastructure\OrdenDetailEntity;

/**
 * @implements ApiProviderInterface<OrdenDetail>
 */
readonly class OrdenDetailProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllOrdenDetailUseCase $getAllUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.producto', 'p'));
            // $context = OptionUtils::addSort($context, new SortRule('orden_id', 'asc'));
            $context = OptionUtils::addFilter($context, new FilterRule('orden', FilterOperation::Equals, $uriVariables['orden_id']));
            $options = OptionsBuilder::build($context, OrdenDetailEntity::class, OrdenDetail::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return null;
    }
}
