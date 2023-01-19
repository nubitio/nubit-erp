<?php

namespace App\{{module}}\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\{{module}}\Domain\{{module}};
use App\{{module}}\Domain\{{module}}CreateUseCase;
use App\{{module}}\Domain\{{module}}DeleteUseCase;
use App\{{module}}\Domain\{{module}}GetOneUseCase;
use App\{{module}}\Domain\{{module}}UpdateUseCase;

readonly class {{module}}Processor implements ProcessorInterface
{
    public function __construct(
        public {{module}}GetOneUseCase $getOneUseCase,
        public {{module}}CreateUseCase ${{module}}CreateUseCase,
        public {{module}}UpdateUseCase ${{module}}UpdateUseCase,
        public {{module}}DeleteUseCase ${{module}}DeleteUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?{{module}}
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->{{module}}DeleteUseCase->__invoke($data->{{entityId}});
            return null;
        }

        $exist = $this->getOneUseCase->__invoke($data->{{entityId}});

        if ($exist) {
            $result = $this->{{module}}UpdateUseCase->__invoke($data);
        } else {
            $result = $this->{{module}}CreateUseCase->__invoke($data);
        }

        return $result;
    }
}