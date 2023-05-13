<?php

namespace App\Role\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Role\Domain\CreateRoleUseCase;
use App\Role\Domain\DeleteRoleUseCase;
use App\Role\Domain\Role;
use App\Role\Domain\UpdateRoleUseCase;

/**
 * @implements ApiProcessorInterface<Role>
 */
readonly class RoleProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateRoleUseCase $createUseCase,
        public UpdateRoleUseCase $updateUseCase,
        public DeleteRoleUseCase $deleteUseCase,
    ) {
    }

    /** {@inheritDoc} */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Role
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->rol_id) {
                $this->deleteUseCase->__invoke($data->rol_id);
            }

            return null;
        }

        if ($data->rol_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
