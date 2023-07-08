<?php

namespace App\Transfer\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Transfer\Domain\Transfer;
use App\Transfer\Domain\CreateTransferUseCase;
use App\Transfer\Domain\DeleteTransferUseCase;
use App\Transfer\Domain\UpdateTransferUseCase;

/**
 * @implements ApiProcessorInterface<Transfer>
 */
readonly class TransferProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateTransferUseCase $createUseCase,
        public UpdateTransferUseCase $updateUseCase,
        public DeleteTransferUseCase $deleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Transfer
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->transfer_id) {
                $this->deleteUseCase->__invoke($data->transfer_id);
            }
            return null;
        }

        if($data->transfer_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}