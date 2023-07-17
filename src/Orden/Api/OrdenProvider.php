<?php

namespace App\Orden\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Orden\Domain\GetAllOrdenUseCase;
use App\Orden\Domain\GetOneOrdenUseCase;
use App\Orden\Domain\Orden;
use App\Orden\Infrastructure\OrdenEntity;

/**
 * @implements ApiProviderInterface<Orden>
 */
readonly class OrdenProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllOrdenUseCase $getAllUseCase,
        private GetOneOrdenUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.mesa', 'm'));
            $context = OptionUtils::addSort($context, new SortRule('orden_id', 'asc'));
            $options = OptionsBuilder::build($context, OrdenEntity::class, Orden::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['orden_id']));
    }
}
