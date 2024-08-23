<?php

namespace App\Employee\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Employee\Domain\CreateEmployeeUseCase;
use App\Employee\Domain\DeleteEmployeeUseCase;
use App\Employee\Domain\Employee;
use App\Employee\Domain\UpdateEmployeeUseCase;

/**
 * @implements ApiProcessorInterface<Employee, Employee>
 */
readonly class EmployeeProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateEmployeeUseCase $createUseCase,
        public UpdateEmployeeUseCase $updateUseCase,
        public DeleteEmployeeUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Employee
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
