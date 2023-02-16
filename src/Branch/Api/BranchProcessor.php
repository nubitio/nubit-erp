<?php

namespace App\Branch\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Branch\Domain\Branch;
use App\Branch\Domain\BranchCreateUseCase;
use App\Branch\Domain\BranchDeleteUseCase;
use App\Branch\Domain\BranchUpdateUseCase;
use App\Core\Domain\Api\ApiProcessorInterface;

readonly class BranchProcessor implements ApiProcessorInterface
{
    public function __construct(
        public BranchCreateUseCase $BranchCreateUseCase,
        public BranchUpdateUseCase $BranchUpdateUseCase,
        public BranchDeleteUseCase $BranchDeleteUseCase,
    )
    {
    }

    /** @inheritDoc */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Branch
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->BranchDeleteUseCase->__invoke($data->local_id);
            return null;
        }

        if ($data->local_id) {
            return $this->BranchUpdateUseCase->__invoke($data);
        }

        return $this->BranchCreateUseCase->__invoke($data);
    }
}