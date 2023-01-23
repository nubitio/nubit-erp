<?php

namespace App\{{module}}\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\{{module}}\Domain\{{module}};
use App\{{module}}\Domain\Create{{module}}UseCase;
use App\{{module}}\Domain\Delete{{module}}UseCase;
use App\{{module}}\Domain\GetOne{{module}}UseCase;
use App\{{module}}\Domain\Update{{module}}UseCase;

readonly class {{module}}Processor implements ProcessorInterface
{
    public function __construct(
        public GetOne{{module}}UseCase $getOneUseCase,
        public Create{{module}}UseCase $createUseCase,
        public Update{{module}}UseCase $updateUseCase,
        public Delete{{module}}UseCase $deleteUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?{{module}}
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->deleteUseCase->__invoke($data->{{entityId}});
            return null;
        }

        $exist = $this->getOneUseCase->__invoke($data->{{entityId}});

        if ($exist) {
            $result = $this->updateUseCase->__invoke($data);
        } else {
            $result = $this->createUseCase->__invoke($data);
        }

        return $result;
    }
}