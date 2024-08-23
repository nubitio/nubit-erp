<?php

namespace App\Invoice\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Invoice\Domain\CreateInvoiceUseCase;
use App\Invoice\Domain\DeleteInvoiceUseCase;
use App\Invoice\Domain\Invoice;
use App\Invoice\Domain\UpdateInvoiceUseCase;

/**
 * @implements ApiProcessorInterface<Invoice, Invoice>
 */
readonly class InvoiceProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateInvoiceUseCase $createUseCase,
        public UpdateInvoiceUseCase $updateUseCase,
        public DeleteInvoiceUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Invoice
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->comprobante_id) {
                $this->deleteUseCase->__invoke($data->comprobante_id);
            }

            return null;
        }

        if ($data->comprobante_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
