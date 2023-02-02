<?php

namespace App\Company\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Company\Domain\Company;
use App\Company\Domain\CreateCompanyUseCase;
use App\Company\Domain\DeleteCompanyUseCase;
use App\Company\Domain\UpdateCompanyUseCase;

readonly class CompanyProcessor implements ProcessorInterface
{
    public function __construct(
        public CreateCompanyUseCase $createUseCase,
        public UpdateCompanyUseCase $updateUseCase,
        public DeleteCompanyUseCase $deleteUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Company
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->deleteUseCase->__invoke($data->empresa_id);
            return null;
        }

        if($data->empresa_id) {
            return $this->updateUseCase->__invoke($data);
        }

        return $this->createUseCase->__invoke($data);
    }
}