<?php

namespace App\Department\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Department\Domain\CreateDepartmentUseCase;
use App\Department\Domain\DeleteDepartmentUseCase;
use App\Department\Domain\Department;
use App\Department\Domain\UpdateDepartmentUseCase;

/**
 * @implements ApiProcessorInterface<Department, Department>
 */
readonly class DepartmentProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateDepartmentUseCase $createUseCase,
        public UpdateDepartmentUseCase $updateUseCase,
        public DeleteDepartmentUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Department
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->id) {
                $this->deleteUseCase->__invoke($data->id);
            }

            return null;
        }

        if ($data->id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
