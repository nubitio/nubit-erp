<?php

namespace App\ConsultationSale\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\ConsultationSale\Domain\ConsultationSale;
use App\ConsultationSale\Domain\CreateConsultationSaleUseCase;
use App\ConsultationSale\Domain\DeleteConsultationSaleUseCase;
use App\ConsultationSale\Domain\UpdateConsultationSaleUseCase;
use App\Core\Domain\Api\ApiProcessorInterface;

/**
 * @implements ApiProcessorInterface<ConsultationSale>
 */
readonly class ConsultationSaleProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateConsultationSaleUseCase $createUseCase,
        public UpdateConsultationSaleUseCase $updateUseCase,
        public DeleteConsultationSaleUseCase $deleteUseCase,
    ) {
    }

    /** {@inheritDoc} */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ConsultationSale
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
