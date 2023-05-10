<?php

namespace App\Position\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Position\Domain\Position;
use App\Position\Domain\GetAllPositionUseCase;
use App\Position\Domain\GetOnePositionUseCase;
use App\Position\Infrastructure\PositionEntity;

/**
 * @implements ApiProviderInterface<Position>
 */
readonly class PositionProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllPositionUseCase $getAllUseCase,
        private GetOnePositionUseCase $getOneUseCase,
    )
    {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.department', 'd'));
            $context = OptionUtils::addSort($context, new SortRule('name', 'asc'));
            $options = OptionsBuilder::build($context, PositionEntity::class, Position::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}