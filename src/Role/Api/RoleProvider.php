<?php

namespace App\Role\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\Role\Domain\GetAllRoleUseCase;
use App\Role\Domain\GetOneRoleUseCase;
use App\Role\Domain\Role;
use App\Role\Infrastructure\RoleEntity;

/**
 * @implements ApiProviderInterface<Role>
 */
readonly class RoleProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllRoleUseCase $getAllUseCase,
        private GetOneRoleUseCase $getOneUseCase,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = OptionUtils::addSort($context, new SortRule('descripcion', 'asc'));
            $options = OptionsBuilder::build($context, RoleEntity::class, Role::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['rol_id']));
    }
}
