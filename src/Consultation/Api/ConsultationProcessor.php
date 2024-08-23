<?php

namespace App\Consultation\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Consultation\Domain\Consultation;
use App\Consultation\Domain\CreateConsultationUseCase;
use App\Consultation\Domain\DeleteConsultationUseCase;
use App\Consultation\Domain\UpdateConsultationUseCase;
use App\Core\Domain\Api\ApiProcessorInterface;

/**
 * @implements ApiProcessorInterface<Consultation, Consultation>
 */
readonly class ConsultationProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateConsultationUseCase $createUseCase,
        public UpdateConsultationUseCase $updateUseCase,
        public DeleteConsultationUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Consultation
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
