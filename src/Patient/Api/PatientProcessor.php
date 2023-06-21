<?php

namespace App\Patient\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Patient\Domain\CreatePatientUseCase;
use App\Patient\Domain\DeletePatientUseCase;
use App\Patient\Domain\Patient;
use App\Patient\Domain\UpdatePatientUseCase;

/**
 * @implements ApiProcessorInterface<Patient>
 */
readonly class PatientProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreatePatientUseCase $createUseCase,
        public UpdatePatientUseCase $updateUseCase,
        public DeletePatientUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Patient
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
