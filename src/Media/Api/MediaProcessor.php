<?php

namespace App\Media\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\Media\Domain\CreateMediaUseCase;
use App\Media\Domain\DeleteMediaUseCase;
use App\Media\Domain\Media;
use App\Media\Domain\UpdateMediaUseCase;

readonly class MediaProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateMediaUseCase $createUseCase,
        public UpdateMediaUseCase $updateUseCase,
        public DeleteMediaUseCase $deleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Media
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->deleteUseCase->__invoke($data->id);
            return null;
        }

        if ($data->id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}