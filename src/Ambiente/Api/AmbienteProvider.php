<?php

namespace App\Ambiente\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Ambiente\Domain\Ambiente;
use App\Ambiente\Domain\GetAllAmbienteUseCase;
use App\Ambiente\Domain\GetOneAmbienteUseCase;
use App\Ambiente\Infrastructure\AmbienteEntity;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;

/**
 * @implements ApiProviderInterface<Ambiente>
 */
readonly class AmbienteProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllAmbienteUseCase $getAllUseCase,
        private GetOneAmbienteUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.local', 'l'));
            $context = OptionUtils::addSort($context, new SortRule('ambiente_id', 'asc'));
            $options = OptionsBuilder::build($context, AmbienteEntity::class, Ambiente::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['ambiente_id']));
    }
}
