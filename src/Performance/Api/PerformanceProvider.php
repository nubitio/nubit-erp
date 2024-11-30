<?php

namespace App\Performance\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\JoinType;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\Performance\Domain\GetAllPerformanceUseCase;
use App\Performance\Domain\GetOnePerformanceUseCase;
use App\Performance\Domain\Performance;
use App\Performance\Infrastructure\PerformanceEntity;

/**
 * @implements ApiProviderInterface<Performance>
 */
readonly class PerformanceProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllPerformanceUseCase $getAllUseCase,
        private GetOnePerformanceUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.lines', 'l', JoinType::LEFT));
            $context = OptionUtils::addJoin($context, new JoinRule('e.products', 'p', JoinType::LEFT));
            $context = OptionUtils::addJoin($context, new JoinRule('e.types', 't', JoinType::LEFT));
            $context = OptionUtils::addSort($context, new SortRule('id', 'desc'));
            $options = OptionsBuilder::build($context, PerformanceEntity::class, Performance::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}
