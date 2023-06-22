<?php

namespace App\Media\Api;

use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Media\Domain\GetOneMediaUseCase;
use App\Media\Domain\Media;

/**
 * @implements ApiProviderInterface<Media>
 */
readonly class MediaProvider implements ApiProviderInterface
{
    public function __construct(
        private GetOneMediaUseCase $getOneUseCase
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->getOneUseCase->__invoke(strval($uriVariables['id']));
    }
}
