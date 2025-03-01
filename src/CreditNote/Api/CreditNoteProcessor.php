<?php

namespace App\CreditNote\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\CreditNote\Domain\CreateCreditNoteUseCase;
use App\CreditNote\Domain\CreditNote;
use App\CreditNote\Domain\DeleteCreditNoteUseCase;
use App\CreditNote\Domain\UpdateCreditNoteUseCase;

/**
 * @implements ApiProcessorInterface<CreditNote, CreditNote>
 */
readonly class CreditNoteProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateCreditNoteUseCase $createUseCase,
        public UpdateCreditNoteUseCase $updateUseCase,
        public DeleteCreditNoteUseCase $deleteUseCase,
    ) {
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): ?CreditNote {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->nota_id) {
                $this->deleteUseCase->__invoke($data->nota_id);
            }

            return null;
        }

        if ($data->nota_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}
