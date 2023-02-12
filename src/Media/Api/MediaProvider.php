<?php

namespace App\Media\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Media\Domain\GetOneMediaUseCase;

readonly class MediaProvider implements ProviderInterface
{
    public function __construct(
        private GetOneMediaUseCase $getOneUseCase
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->getOneUseCase->__invoke($uriVariables['id']);
    }
}