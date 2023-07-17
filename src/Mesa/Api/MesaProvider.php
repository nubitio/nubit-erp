<?php

namespace App\Mesa\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Mesa\Domain\GetAllMesaUseCase;
use App\Mesa\Domain\GetOneMesaUseCase;
use App\Mesa\Domain\Mesa;
use App\Mesa\Infrastructure\MesaEntity;

/**
 * @implements ApiProviderInterface<Mesa>
 */
readonly class MesaProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllMesaUseCase $getAllUseCase,
        private GetOneMesaUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.ambiente', 'a'));
            $context = OptionUtils::addSort($context, new SortRule('mesa_id', 'asc'));
            $options = OptionsBuilder::build($context, MesaEntity::class, Mesa::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['mesa_id']));
    }
}
