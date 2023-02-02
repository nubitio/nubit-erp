<?php

namespace App\Role\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Infrastructure\DataLoader\OptionsBuilder;
use App\Core\Infrastructure\DataLoader\OptionUtils;
use App\Core\Infrastructure\DataLoader\SortRule;
use App\Role\Domain\GetAllRoleUseCase;
use App\Role\Domain\GetOneRoleUseCase;
use App\Role\Infrastructure\RoleEntity;

readonly class RoleProvider implements ProviderInterface
{
    public function __construct(
        private GetAllRoleUseCase $getAllUseCase,
        private GetOneRoleUseCase $getOneUseCase,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('descripcion', 'asc'));
            $options = OptionsBuilder::build($context, RoleEntity::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke($uriVariables['rol_id']);
    }
}