<?php

namespace App\ComboOption\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\ComboOption\Domain\ComboOption;
use App\ComboOption\Domain\CreateComboOptionUseCase;
use App\ComboOption\Domain\DeleteComboOptionUseCase;
use App\ComboOption\Domain\UpdateComboOptionUseCase;

/**
 * @implements ApiProcessorInterface<ComboOption>
 */
readonly class ComboOptionProcessor implements ApiProcessorInterface
{
    public function __construct(
        public CreateComboOptionUseCase $createUseCase,
        public UpdateComboOptionUseCase $updateUseCase,
        public DeleteComboOptionUseCase $deleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ComboOption
    {
        if ($operation instanceof DeleteOperationInterface) {
            if ($data->id) {
                $this->deleteUseCase->__invoke($data->id);
            }
            return null;
        }

        if($data->id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}