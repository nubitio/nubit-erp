<?php

namespace App\Consult\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Consult\Domain\ConsultUseCase;
use App\Consult\Domain\DocType;

readonly class ConsultProvider implements ProviderInterface
{
    public function __construct(
        private ConsultUseCase $consultUseCase
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['dni'])) {
            return $this->consultUseCase->consult($uriVariables['dni'], DocType::DNI);
        }

        return $this->consultUseCase->consult($uriVariables['ruc'], DocType::RUC);
    }
}