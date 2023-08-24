<?php

namespace App\Kardex\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Kardex\Domain\GetAllKardexUseCase;
use App\Kardex\Domain\Kardex;

/**
 * @implements ApiProviderInterface<Kardex>
 */
readonly class KardexProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllKardexUseCase $getAllKardexUseCase,
    ) {
    }

    /** inheritdoc */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->getAllKardexUseCase->__invoke();
        }

        return null;
    }
}
