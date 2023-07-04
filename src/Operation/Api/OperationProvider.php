<?php

namespace App\Operation\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation as ApiOperation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Operation\Domain\GetAllOperationUseCase;
use App\Operation\Domain\GetOneOperationUseCase;
use App\Operation\Domain\Operation;
use App\Operation\Infrastructure\OperationEntity;

/**
 * @implements ApiProviderInterface<Operation>
 */
readonly class OperationProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllOperationUseCase $getAllUseCase,
        private GetOneOperationUseCase $getOneUseCase,
    ) {
    }

    public function provide(ApiOperation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('descripcion', 'asc'));
            $options = OptionsBuilder::build($context, OperationEntity::class, Operation::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['operacion_id']));
    }
}
