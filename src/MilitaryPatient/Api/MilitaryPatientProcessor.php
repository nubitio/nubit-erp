<?php

namespace App\MilitaryPatient\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\MilitaryPatient\Domain\CreateMilitaryPatientUseCase;
use App\MilitaryPatient\Domain\DeleteMilitaryPatientUseCase;
use App\MilitaryPatient\Domain\MilitaryPatient;
use App\MilitaryPatient\Domain\UpdateMilitaryPatientUseCase;

/**
 * @implements ApiProcessorInterface<MilitaryPatient>
 */
readonly class MilitaryPatientProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateMilitaryPatientUseCase $createUseCase,
        public UpdateMilitaryPatientUseCase $updateUseCase,
        public DeleteMilitaryPatientUseCase $deleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?MilitaryPatient
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->id) {
                $this->deleteUseCase->__invoke($data->id);
            }
            return null;
        }

        if ($data->id || intval($uriVariables['id'] ?? 0) > 0) {
            if ($data->id) {
                $data->id = intval($data->id);
            } else {
                $data->id = intval($uriVariables['id']);
            }
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}