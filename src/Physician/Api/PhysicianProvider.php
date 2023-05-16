<?php

namespace App\Physician\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Infrastructure\DataLoader\JoinRule;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Physician\Domain\GetAllPhysicianUseCase;
use App\Physician\Domain\GetOnePhysicianUseCase;
use App\Physician\Domain\Physician;
use App\Physician\Infrastructure\PhysicianEntity;

/**
 * @implements ApiProviderInterface<Physician>
 */
readonly class PhysicianProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllPhysicianUseCase $getAllUseCase,
        private GetOnePhysicianUseCase $getOneUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addJoin($context, new JoinRule('e.employee', 'y'));
            $context = OptionUtils::addJoin($context, new JoinRule('e.specialty', 's'));
            $context = OptionUtils::addSort($context, new SortRule('id', 'asc'));
            $options = OptionsBuilder::build($context, PhysicianEntity::class, Physician::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(intval($uriVariables['id']));
    }
}
