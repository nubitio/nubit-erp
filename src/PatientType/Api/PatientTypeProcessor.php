<?php

namespace App\PatientType\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\PatientType\Domain\CreatePatientTypeUseCase;
use App\PatientType\Domain\DeletePatientTypeUseCase;
use App\PatientType\Domain\PatientType;
use App\PatientType\Domain\UpdatePatientTypeUseCase;

/**
 * @implements ApiProcessorInterface<PatientType>
 */
readonly class PatientTypeProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreatePatientTypeUseCase $createUseCase,
        public UpdatePatientTypeUseCase $updateUseCase,
        public DeletePatientTypeUseCase $deleteUseCase,
    ) {
    }

    /** {@inheritDoc} */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?PatientType
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
