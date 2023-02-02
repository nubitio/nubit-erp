<?php

namespace App\Role\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Role\Domain\Role;
use App\Role\Domain\CreateRoleUseCase;
use App\Role\Domain\DeleteRoleUseCase;
use App\Role\Domain\UpdateRoleUseCase;

readonly class RoleProcessor implements ProcessorInterface
{
    public function __construct(
        public CreateRoleUseCase $createUseCase,
        public UpdateRoleUseCase $updateUseCase,
        public DeleteRoleUseCase $deleteUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Role
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->deleteUseCase->__invoke($data->rol_id);
            return null;
        }

        if($data->rol_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}