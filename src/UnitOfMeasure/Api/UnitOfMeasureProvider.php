<?php

namespace App\UnitOfMeasure\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\UnitOfMeasure\Domain\GetAllUnitOfMeasureUseCase;
use App\UnitOfMeasure\Domain\GetOneUnitOfMeasureUseCase;
use App\UnitOfMeasure\Domain\UnitOfMeasure;
use App\UnitOfMeasure\Infrastructure\UnitOfMeasureEntity;

/**
 * @implements ApiProviderInterface<UnitOfMeasure>
 */
readonly class UnitOfMeasureProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllUnitOfMeasureUseCase $getAllUseCase,
        private GetOneUnitOfMeasureUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('name', 'asc'));
            $options = OptionsBuilder::build($context, UnitOfMeasureEntity::class, UnitOfMeasure::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['unidad_id']));
    }
}
