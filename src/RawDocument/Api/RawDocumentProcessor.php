<?php

namespace App\RawDocument\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\RawDocument\Domain\CreateRawDocumentUseCase;
use App\RawDocument\Domain\DeleteRawDocumentUseCase;
use App\RawDocument\Domain\RawDocument;
use App\RawDocument\Domain\UpdateRawDocumentUseCase;

/**
 * @implements ApiProcessorInterface<RawDocument, RawDocument>
 */
readonly class RawDocumentProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateRawDocumentUseCase $createUseCase,
        public UpdateRawDocumentUseCase $updateUseCase,
        public DeleteRawDocumentUseCase $deleteUseCase,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?RawDocument
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
