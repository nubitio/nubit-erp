<?php

namespace App\Performance\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Performance\Domain\Performance;
use App\Performance\Domain\GetAllPerformanceUseCase;
use App\Performance\Domain\GetOnePerformanceUseCase;
use App\Performance\Infrastructure\PerformanceEntity;

/**
 * @implements ApiProviderInterface<Performance>
 */
readonly class PerformanceProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllPerformanceUseCase $getAllUseCase,
        private GetOnePerformanceUseCase $getOneUseCase,
    )
    {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('id', 'desc'));
            $options = OptionsBuilder::build($context, PerformanceEntity::class, Performance::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}