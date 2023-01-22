<?php

namespace App\Branch\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Branch\Domain\Branch;
use App\Branch\Domain\BranchCreateUseCase;
use App\Branch\Domain\BranchDeleteUseCase;
use App\Branch\Domain\BranchGetOneUseCase;
use App\Branch\Domain\BranchUpdateUseCase;

readonly class BranchProcessor implements ProcessorInterface
{
    public function __construct(
        public BranchGetOneUseCase $getOneUseCase,
        public BranchCreateUseCase $BranchCreateUseCase,
        public BranchUpdateUseCase $BranchUpdateUseCase,
        public BranchDeleteUseCase $BranchDeleteUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Branch
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->BranchDeleteUseCase->__invoke($data->local_id);
            return null;
        }

        $exist = $this->getOneUseCase->__invoke($data->local_id);

        if ($exist) {
            $result = $this->BranchUpdateUseCase->__invoke($data);
        } else {
            $result = $this->BranchCreateUseCase->__invoke($data);
        }

        return $result;
    }
}