<?php

namespace App\Consult\Api;

use ApiPlatform\Metadata\Operation;
use App\Consult\Domain\Consult;
use App\Consult\Domain\ConsultUseCase;
use App\Consult\Domain\DocType;
use App\Core\Domain\Api\ApiProviderInterface;

/**
 * @implements ApiProviderInterface<Consult>
 */
readonly class ConsultProvider implements ApiProviderInterface
{
    public function __construct(
        private ConsultUseCase $consultUseCase
    ) {
    }

    /** {@inheritDoc} */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['dni'])) {
            return $this->consultUseCase->consult(strval($uriVariables['dni']), DocType::DNI);
        }

        return $this->consultUseCase->consult(strval($uriVariables['ruc']), DocType::RUC);
    }
}
